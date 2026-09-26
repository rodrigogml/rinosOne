# Plano técnico — Fundação de armazenamento de arquivos

## Resumo

Será criada uma fundação privada, global e independente de módulos para conteúdo deduplicado, versões ramificáveis, posses por usuário ou tenant, retenção e múltiplos backends configuráveis. A extensão de drive adiciona pastas lógicas para workspaces de usuário e tenant; compartilhamento é decidido por `resource-authorization`, sem expor paths físicos.

## Contexto técnico

- Laravel 12 e PHP 8.2+; ambiente local atual em PHP 8.5.
- MySQL 9 no schema principal `rinosone`.
- O primeiro consumidor é Perfil, mas os serviços não dependem de sua interface.
- A imagem de avatar exige GD com JPEG, PNG e WebP. A extensão não está disponível no runtime verificado e é pré-requisito de deploy.

## Arquitetura de ingestão

1. O consumidor valida autorização e grava em staging privado.
2. O serviço calcula SHA-256 e metadados, identifica ou cria `file_fileContent` sob concorrência.
3. A representação física é promovida atomicamente para o backend ativo, usando caminho content-addressed.
4. Uma transação cria ou reutiliza conteúdo, versão, posse e totais de uso.
5. Uma falha após escrita física preserva o objeto temporariamente como órfão para reconciliação; uma falha antes da promoção não cria referência no banco.

O banco é autoridade das referências. O filesystem nunca é indexado diretamente pela interface.

## Versões, posses e retenção

- Versão possui pai opcional e número sequencial por arquivo lógico.
- Posse define dono, nome, versão atual, área, estado e cota; uma posse não é compartilhada implicitamente com outra.
- Exclusão do workspace vai para lixeira por padrão; lixeira segue consumindo quota.
- Limpeza manual remove a posse de modo irrecuperável. Conteúdo físico só se torna elegível quando não houver referência e a maior retenção configurada tiver passado.
- Rotinas agendadas tratam expurgo, recompactação e reconciliação de órfãos.

## Drive e pastas lógicas

- `file_workspaceFolder` pertence exclusivamente a um usuário ou tenant, possui raiz implícita e pode referenciar uma pasta pai do mesmo workspace. Quando enviada à lixeira, recebe `purgeAfter` pela mesma política das posses e só é restaurada como conjunto válido.
- `file_filePossession` recebe vínculo opcional para a pasta lógica; nulo significa raiz do workspace. Nomes de pastas ativas são únicos entre irmãos do mesmo workspace.
- A lixeira de uma pasta é recursiva: transiciona pasta, descendentes e posses vinculadas. O restauro recompõe a árvore enquanto todas as retenções permitirem.
- A árvore é protegida contra ciclos, cruzamento de workspace e vínculo de posse pertencente a outro proprietário.
- A pasta é registrada como tipo de recurso `personal.folder` ou `tenant.folder`; relations concedidas ao nó são herdadas por descendentes. O adaptador comprova a existência e o proprietário no catálogo `file_*`.

## Configuração de implantação

O arquivo ambiente conterá valores reais; o modelo versionado explicará cada chave.

```dotenv
FILE_STORAGE_DEFAULT_BACKEND=local-private
FILE_STORAGE_LOCAL_PRIVATE_DISK=file-private
FILE_STORAGE_LOCAL_PRIVATE_PATH=/srv/rinosone/file-storage
FILE_STORAGE_LOCAL_PRIVATE_STATE=ACTIVE
FILE_STORAGE_BACKEND_DEFINITIONS={}
FILE_TRASH_RETENTION_DAYS=30
FILE_BACKUP_RETENTION_DAYS=60
FILE_TECHNICAL_RETENTION_DAYS=60
FILE_ORPHAN_RETENTION_DAYS=60
FILE_COMPRESSION_REPROCESS_INTERVAL_MINUTES=60
```

Validação de configuração exigirá `FILE_TECHNICAL_RETENTION_DAYS >= FILE_BACKUP_RETENTION_DAYS`. Credenciais e caminhos de cada backend ficam no driver Laravel ou no ambiente, nunca nas tabelas.

## Estrutura prevista

| Caminho | Responsabilidade |
| --- | --- |
| `app/Domain/FileStorage/Content` | Regras de hash, conteúdo e deduplicação. |
| `app/Domain/FileStorage/Possession` | Posse, versões, bindings e quota. |
| `app/Domain/FileStorage/Retention` | Elegibilidade, lixeira e reconciliação. |
| `app/Infrastructure/FileStorage` | Discos privados, staging e promoção física. |
| `app/Contracts/FileStorage/V1` | Porta interna versionada e DTOs compatíveis para consumidores. |
| `app/Services/FileStorage` | Operações do contrato interno. |
| `app/Services/FileStorage/Workspace` | Pastas, árvore lógica e transições recursivas de lixeira. |
| `app/Jobs/FileStorage` | Expurgo, recompactação e limpeza de órfãos. |
| `database/migrations/core` | Catálogo global `file_*`. |
| `config/file-storage.php` | Política de retenção e resolução de backends. |
| `config/filesystems.php` | Disco privado `file-private`, sem exposição pública. |

## Fronteiras e contratos

O contrato interno está em [file-storage-internal.md](contracts/file-storage-internal.md) e inicia em `FileStorageV1`. Não haverá endpoint público nesta fase. O Perfil consumirá `storeManagedVersion` e `authorizePrivateRead`; módulos futuros de tenant usarão a mesma API de domínio sem referenciar caminhos físicos. Mudanças incompatíveis de contrato coexistirão em uma nova versão, em vez de alterar consumidores silenciosamente.

O modelo também reserva `file_versionDerivative` para associar futuras thumbnails ou representações a uma versão de origem. A entrega atual não gera, lista nem entrega derivadas.

## Checagem de constituição

| Princípio | Resultado | Evidência |
| --- | --- | --- |
| Incrementalidade | PASS | Drive, compartilhamento e thumbnails permanecem fora do escopo. |
| Segurança | PASS | Discos privados, autorização por contexto e hashes de integridade. |
| Persistência sustentável | PASS | Catálogo global, FKs, retenção e reconciliação explícitas. |
| Configuração externa | PASS | Backends e prazos não são codificados no banco. |
| Qualidade verificável | PASS | Cenários de dedup, lixeira, ramificação e retenção definidos. |
| Incrementalidade do drive | PASS | Apenas árvore e lifecycle de pasta; listagem e editor pertencem a `resource-authorization`. |

## Próxima etapa

Criar checklist de requisitos para a fundação. O design de interface não se aplica diretamente, pois a fundação não entrega uma superfície humana nesta fase.
