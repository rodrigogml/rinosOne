# Operação de Provisionamento de Tenants

Esta referência define a separação mínima entre as credenciais de banco da aplicação e a credencial usada exclusivamente pelo worker de provisionamento. Valores concretos pertencem ao `.env` da instância e não devem ser registrados em documentação, tickets ou logs.

## Conexões

| Conexão | Finalidade | Credencial | Regra |
| --- | --- | --- | --- |
| `core` | Identidade, autenticação e plano de controle global em `rinosone`. | Runtime global (`DB_*`). | Não executa DDL nem cria schemas de tenant. |
| `coreMigration` | Aplicação do catálogo global de migrations. | Migration global (`CORE_MIGRATION_*`). | É usada somente por `php artisan migrate:global`; não atende requisições web ou workers. |
| `tenant` | Dados de um tenant já autorizado. | Runtime de tenant (`TENANT_RUNTIME_*`). | O database é derivado internamente do `BIGINT UNSIGNED` validado; não recebe nome pela API. |
| `provisioning` | Criação e preparação física de schema. | Provisionamento (`TENANT_PROVISIONING_*`). | É usada apenas pelo worker de provisionamento; nunca por requisições web. |

## Privilégios mínimos

O usuário de runtime global deve ter somente operações de leitura e escrita necessárias em `rinosone`. O usuário de runtime de tenant deve ter somente operações de leitura e escrita nos schemas de tenant aos quais a aplicação pode acessar. Nenhum deles recebe `CREATE DATABASE`, `DROP DATABASE`, `CREATE USER`, `GRANT OPTION`, `FILE`, privilégios administrativos ou acesso direto ao servidor.

O usuário de migration global é separado do runtime e limitado a `rinosone.*`. Para executar o catálogo atual, precisa de `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `REFERENCES`, `INDEX`, `ALTER`, `CREATE TEMPORARY TABLES` e `LOCK TABLES`. Ele não recebe `CREATE DATABASE`, `DROP DATABASE`, `CREATE USER`, `GRANT OPTION`, `FILE` ou acesso aos schemas `rinosone_*` de tenants.

O usuário de provisionamento é separado e usado somente pelo worker. Ele necessita criar um schema de tenant e a tabela de histórico de migrations, além de aplicar DDL aditivo e os dados mínimos de migrations. Para migrations de domínio que criam FKs tenant → core, conceda a ele somente o privilégio `REFERENCES` em `rinosone.*`; não conceda escrita nem DDL no schema core por esse motivo. Não conceda `DROP DATABASE`, administração de usuários ou `GRANT OPTION`.

> [!WARNING]
> MySQL não oferece um privilégio nativo que limite `CREATE DATABASE` a um prefixo de nome. O acesso de criação deve ficar isolado na credencial do worker, em ambiente restrito e com monitoramento operacional. A derivação interna do nome `rinosone_{tenantId}` é a barreira da aplicação contra entrada arbitrária.

## Configuração e implantação

Configure `CORE_MIGRATION_*`, `TENANT_RUNTIME_*` e `TENANT_PROVISIONING_*` com contas distintas do runtime global. Mantenha `TENANT_PROVISIONING_DATABASE` vazio quando o servidor permitir conexão sem schema inicial; se a infraestrutura exigir um schema de conexão, informe somente um schema operacional já existente e sem dados de tenant.

Os valores `TENANT_PROVISIONING_MAXIMUM_ATTEMPTS` e `TENANT_PROVISIONING_RETRY_DELAYS_MINUTES` controlam a política que será aplicada pelo worker. O padrão é três tentativas com esperas de 1, 5 e 15 minutos.

Antes de liberar o worker, valide em uma instância descartável que a credencial de runtime não consegue criar schemas e que a credencial de provisionamento não é utilizada pela web.

## Execução do worker

O job `ProvisionTenantSchema` recebe somente o identificador da operação global. Ele reivindica a operação em estado `QUEUED`, deriva o schema de seu tenant, cria o schema quando necessário, executa exclusivamente `database/migrations/tenant/` e confere se todas as migrations esperadas constam no histórico daquele schema.

Somente depois dessa confirmação a operação muda para `SUCCEEDED` e o tenant para `ACTIVE`. Falhas transitórias retornam a operação para `QUEUED` respeitando os intervalos configurados. Falhas terminais e esgotamento de tentativas marcam a operação e o tenant como `FAILED`, mantendo-os indisponíveis para contexto operacional.

Execute o worker supervisionado com a mesma fila persistida usada pela aplicação:

```sh
php artisan queue:work database --sleep=1 --max-time=3600
```

O serviço de criação agenda esse job somente após a confirmação da transação global. A API versionada de tenants não pode criar tenant, vínculo ou job fora dessa transação.

## Sequência segura de deploy

1. Configure no ambiente as quatro conexões (`core`, `coreMigration`, runtime de tenant e provisionamento), sempre com credenciais distintas.
2. Execute `php artisan migrate:global --force` antes de expor a versão da aplicação.
3. Verifique o worker com uma instância descartável: criar um tenant deve gerar um schema com `utf8mb4` e `utf8mb4_unicode_ci`, contendo apenas o catálogo `database/migrations/tenant/`.
4. Inicie o worker supervisionado e acompanhe somente os estados seguros `QUEUED`, `RUNNING`, `SUCCEEDED` e `FAILED`.
5. Libere a web depois de confirmar que um tenant só aparece como selecionável após `ACTIVE`.

## Recuperação segura

Uma falha transitória retorna a preparação para `QUEUED` e aplica os intervalos configurados. Depois do máximo de tentativas, ou diante de falha terminal, a preparação e o tenant ficam `FAILED`; não recrie tenant, vínculo ou schema manualmente a partir da interface. Corrija a causa operacional, avalie a preparação existente e dispare uma recuperação operacional idempotente usando a mesma operação. Nenhum tenant em falha ou preparação pode iniciar contexto.

## Evidências de validação

Em 2026-09-24, a validação local confirmou em uma instância MySQL descartável que o catálogo global e o catálogo de tenant não se misturam; o schema de tenant recebeu uma baseline independente, com o charset e a collation previstos. As contas temporárias, schemas de validação e grants foram removidos após a inspeção. A suíte de estados exercitou sucesso, retomada, falhas transitórias, falhas terminais e o limite configurável de retentativas sem registrar segredos, SQL, host ou nomes de schemas da instância.

Em 2026-09-25, a conta local exclusiva de migration global aplicou a conversão de identificadores do schema `rinosone`. A inspeção confirmou PKs `BIGINT UNSIGNED` nas tabelas globais convertidas e FKs com `ON UPDATE CASCADE`, além de `ON DELETE CASCADE` ou `SET NULL` conforme a opcionalidade. Nenhum valor de credencial foi registrado.
