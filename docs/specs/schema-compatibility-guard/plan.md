# Plano de Implementação: Compatibilidade de Schemas e Guarda Operacional

**Feature**: `schema-compatibility-guard` | **Data**: 2026-09-30 | **Spec**: [spec.md](spec.md)

## Resumo

Implementar uma guarda de compatibilidade que impede a plataforma de atender funções de negócio quando o catálogo global não estiver aplicado e impede isoladamente o uso de uma organização com schema atrasado. A atualização global permanece uma responsabilidade explícita do deploy. Organizações existentes são atualizadas por ciclo operacional assíncrono, serializado e idempotente, usando a credencial já separada de provisionamento.

A fonte de verdade será o catálogo de migrations distribuído com o código e o histórico aplicado em cada schema. Não haverá versão manual paralela.

## Contexto técnico

**Linguagens/versões**: PHP 8.2+ e Laravel 12 no backend; TypeScript 5.7, Vue 3.5 e Pinia 4 na web.
**Dependências principais**: Laravel migrations, Eloquent, fila persistida, scheduler, Axios, Vue I18n e sistema de design próprio.
**Armazenamento**: MySQL; schema global `rinosone`, schemas isolados `rinosone_{tenantId}`, histórico de migrations por schema, sessões e fila persistida no schema global.
**Testes**: PHPUnit, Vitest, Playwright, verificação de tipos e build de produção.
**Plataforma-alvo**: web responsiva e API JSON versionada; worker e scheduler supervisionados.
**Tipo de projeto**: monólito modular com SPA web e API.
**Metas de desempenho**: a decisão de compatibilidade não executa regra de negócio nem DDL durante uma solicitação; a verificação pode usar janela curta e configurável de cache em processo, com falha sempre tratada como indisponibilidade.
**Restrições**: credenciais de runtime nunca executam DDL; a credencial de migration global não atende HTTP; a credencial de provisionamento atualiza somente schemas de tenant; erros técnicos não atravessam a interface ou API pública.
**Escopo**: bloqueio global, bloqueio por organização, atualização assíncrona de organizações existentes, integração do provisionamento inicial e evidência operacional segura.

## Arquitetura das superfícies de interação

**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md).
**Aplicabilidade de Interface Design**: REQUIRED — a indisponibilidade global e a indisponibilidade contextual de uma organização alteram estados e recuperação na web responsiva.

| Surface ID | Cobertura da feature | Decisão tecnológica | Módulo/repositório | Notas |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | FULL | Vue 3, TypeScript e navegador | `resources/js`, `resources/css`, `resources/views` | Exibe a indisponibilidade global sem inicializar fluxos funcionais; trata a indisponibilidade de organização no seletor e na área de trabalho. |
| SURF-WEB-PEOPLE | PARTIAL | Vue 3, TypeScript e navegador | `resources/js/people`, `app`, `routes/api` | Reutiliza a guarda contextual; não cria tratamento específico de Pessoas. |
| SURF-WEB-DRIVE | PARTIAL | Vue 3, TypeScript e navegador | `resources/js/drive`, `app`, `routes/api` | Reutiliza a guarda contextual; não cria tratamento específico de Drive. |
| SURF-FUTURE-CONSUMERS | PARTIAL | API JSON versionada | `app`, `routes/api` | Recebe códigos estáveis de indisponibilidade sem depender de texto. |

## Constitution Check

*GATE: aprovado antes da pesquisa e rechecado após o desenho.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | Cria somente a proteção e o ciclo operacional necessários para schemas existentes; não introduz gestão de deploy pela interface. |
| II. Fronteira API e domínio independente da interface | PASS | A decisão de compatibilidade pertence ao backend; a web apenas interpreta o contrato estável. |
| III. Identidade e acesso seguros por padrão | PASS | Bloqueia antes da regra de negócio e não revela informações de infraestrutura. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Não adiciona segredos ou estado contextual à sessão; credenciais já separadas são preservadas. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Define contratos, cenários e cobertura de backend, frontend e E2E antes das tarefas. |
| VI. Identidades numéricas e referências unidirecionais | PASS | A atualização pertence ao core, referencia tenant com BIGINT e não cria referência inversa do core para dados de tenant. |

## Desenho da arquitetura

### Guarda global

1. Um serviço de compatibilidade carrega o catálogo global disponível e o compara ao histórico aplicado no schema global.
2. Um middleware de maior precedência protege páginas e endpoints funcionais antes de autenticação de negócio, controllers, jobs disparados por HTTP ou acesso a dados.
3. Quando a leitura falhar ou houver migration pendente, a web recebe uma página segura de indisponibilidade e a API recebe `503 PLATFORM_SCHEMA_INCOMPATIBLE`.
4. O mecanismo não executa migrations. Endpoints de saúde e os mecanismos não HTTP indispensáveis para executar a recuperação são explicitamente isentos e não expõem detalhes publicamente.
5. O resultado pode ser reutilizado apenas pelo intervalo configurado; expirada a janela, a próxima solicitação revalida. Uma falha de cache ou leitura nunca concede acesso.

### Guarda por organização

1. O serviço de compatibilidade consulta o catálogo de tenant e o histórico do schema da organização, além de qualquer ciclo de atualização pendente ou falho.
2. A resolução de contexto e toda aquisição de conexão de runtime passam por uma porta que exige disponibilidade organizacional.
3. A API retorna `503 TENANT_SCHEMA_UNAVAILABLE` para a organização incompatível; a web limpa o contexto afetado e informa estado temporário localizado.
4. Recursos pessoais e demais organizações não são afetados. A indisponibilidade administrativa já existente continua distinta da indisponibilidade por atualização.

### Atualização de organizações existentes

1. Após uma versão ser liberada com catálogo de tenant novo, o processo operacional supervisionado descobre organizações cujo histórico esteja incompleto.
2. Para cada organização, cria ou reutiliza um ciclo `tenantSchemaUpdate` e impede a duplicidade por organização e catálogo alvo.
3. O worker reivindica o ciclo de forma transacional, marca-o em execução e usa exclusivamente a conexão de provisionamento para aplicar o catálogo de tenant.
4. O worker confere o histórico completo após a execução. Somente então marca sucesso e a guarda volta a permitir contexto.
5. Falhas transitórias usam a política configurada de tentativas e espera; falhas terminais mantêm a organização indisponível. Nenhum job do runtime web executa DDL.
6. O provisionamento inicial chama o mesmo verificador final de catálogo, mantendo sua transição para ativa somente após compatibilidade completa.

### Operação e deploy

1. Colocar a versão nova em janela de implantação conforme política operacional.
2. Executar `migrate:global --force` com a credencial global exclusiva.
3. Confirmar que a guarda global reconhece compatibilidade.
4. Liberar a aplicação e iniciar ou retomar o processo de atualização de organizações existentes.
5. Monitorar ciclos pendentes, concluídos e falhos; organizações só voltam ao uso após confirmação.
6. Reiniciar workers depois de uma implantação, conforme a prática operacional atual, para que executem o código novo.

## Modelo de dados e estados

O detalhamento está em [data-model.md](data-model.md).

- O histórico de migrations existente continua sendo a autoridade para compatibilidade.
- A nova entidade `tenantSchemaUpdate` é um ciclo operacional do core, separado de `tenantProvisioning`, pois atende organizações já ativas e preserva histórico próprio.
- A organização não muda para um estado administrativo genérico durante atualização; a guarda calcula sua indisponibilidade a partir do ciclo de atualização. Isso preserva a diferença entre “inativa por decisão administrativa” e “indisponível por atualização”.
- A retenção da evidência operacional segue a política configurável do ambiente, com o padrão geral vigente de 90 dias, sem registrar mensagens de banco ou segredos.

## Contratos

- [schema-compatibility.md](contracts/schema-compatibility.md) define `PLATFORM_SCHEMA_INCOMPATIBLE` e `TENANT_SCHEMA_UNAVAILABLE`, ambos como indisponibilidade temporária.
- A resposta global é usada pela interface e por todos os consumidores funcionais.
- A resposta contextual é usada para seleção de organização e qualquer operação de tenant.
- Nenhum contrato público apresenta migration pendente, versão esperada, schema físico ou código de falha interno.

## Estrutura do projeto

### Documentação desta feature

```text
docs/specs/schema-compatibility-guard/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── contracts/
    └── schema-compatibility.md
```

### Código a criar ou alterar

```text
app/
├── Console/Commands/                 # coordenação operacional e inspeção segura
├── Domain/Tenant/                    # estados e exceções de compatibilidade
├── Http/Middleware/                  # guarda global e resposta HTTP
├── Infrastructure/Tenant/            # leitura de catálogo e conexões de runtime/provisionamento
├── Jobs/                             # atualização assíncrona por organização
└── Services/Tenant/                  # decisão de compatibilidade e ciclo de atualização
bootstrap/app.php                     # precedência da guarda global
config/                               # política de verificação, cache e retentativa
database/migrations/core/             # ciclo de atualização de organizações existentes
resources/js/                         # tratamento localizado de indisponibilidade global e contextual
resources/views/                      # página segura de indisponibilidade global
routes/console.php                    # descoberta e retomada supervisionadas
tests/
├── Feature/                          # bloqueios, transições e contratos
├── Unit/                             # comparação de catálogos e classificação
├── js/                               # clientes e stores da web
└── e2e/                              # estados responsivos críticos
```

**Decisão de estrutura**: a comparação de catálogos e decisão de disponibilidade ficam no contexto de tenant e não em módulos de Pessoas, Drive ou autorização. Cada módulo consumidor continua usando a resolução contextual comum.

## Convenções de borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Histórico de migrations | nomes de migration existentes | comparação de catálogo completo | diretórios `database/migrations/core` e `database/migrations/tenant` |
| Colunas do core | camelCase | migration, constraints e testes de persistência | `database/migrations/core/` |
| Backend DTO e erros | camelCase | middleware, responses e testes de feature | `contracts/schema-compatibility.md` |
| Frontend DTO e estado | camelCase | tipos, testes unitários e E2E | `resources/js/` e contrato |
| API payload | camelCase | testes de contrato ponta a ponta | `contracts/schema-compatibility.md` |
| Códigos de indisponibilidade | UPPER_SNAKE_CASE | resposta HTTP e testes de contrato | `contracts/schema-compatibility.md` |

**Mapper layer (DB ↔ DTO)**: os serviços de compatibilidade traduzem o histórico de migrations e o ciclo operacional para decisões de domínio; middleware e controladores traduzem essas decisões no contrato HTTP.

**Validação de schema**: requests não aceitam versão ou schema informado pelo cliente. As respostas são validadas por testes de feature, tipos de cliente e roundtrip E2E.

## Validação planejada

Os cenários executáveis estão em [quickstart.md](quickstart.md).

- Testes unitários para catálogo completo, histórico ausente, erro de leitura, janela de revalidação e classificação de disponibilidade.
- Testes de feature para guarda global anterior a controllers e para contrato seguro em web/API.
- Testes de feature para bloqueio de uma organização sem afetar outra, idempotência, lock e novas tentativas do ciclo.
- Testes de integração para aplicação do catálogo por uma conexão de provisionamento em schema descartável.
- Testes JavaScript e E2E para página global, seletor de organização e limpeza de contexto ativo.
- `php artisan migrate:status` nos catálogos global e tenant, mais testes, type-check e build antes da entrega.

## Complexity Tracking

Nenhuma violação da Constituição foi identificada. A separação entre provisionamento inicial e atualização posterior é necessária porque representam ciclos de vida diferentes e impede reuso semântico incorreto de uma operação única de criação.
