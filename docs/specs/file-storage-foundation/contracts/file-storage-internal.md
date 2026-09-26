# Contrato interno versionado — Fundação de armazenamento de arquivos

Este é o contrato `FileStorageV1`. Ele não expõe endpoints de drive nesta fase; define uma porta interna versionada a ser consumida por módulos autenticados, sem acoplamento a HTTP, Vue ou caminhos físicos.

## Versionamento e compatibilidade

- Implementações e consumidores usam a fronteira `FileStorageV1` e seus DTOs no namespace de contrato `V1`.
- Inclusões opcionais e novos métodos são compatíveis dentro de V1; renomear, remover ou alterar semântica de entrada, retorno ou erro cria `FileStorageV2` paralela.
- O Perfil consome somente V1. A troca para versão posterior será explícita por consumidor, mantendo V1 durante a migração.
- O número de versão é da fronteira de aplicação, não da estrutura de banco nem do caminho físico. Nenhum endpoint público é inferido por esta decisão.

## `storeManagedVersion`

Armazena bytes recebidos, deduplica conteúdo, cria versão e associa ou substitui uma posse `SYSTEM_MANAGED`.

| Entrada | Descrição |
| --- | --- |
| `owner` | Usuário ou tenant autorizado. |
| `purpose` | Finalidade controlada pelo recurso, como `USER_PROFILE_AVATAR`. |
| `sourceStream` | Fluxo de bytes já validado pelo consumidor. |
| `displayName` | Nome lógico técnico, não visível no futuro drive. |
| `parentVersionId` | Origem opcional de uma nova ramificação. |
| `idempotencyKey` | Chave do comando para impedir duplicidade em repetição. |

Retorna `possessionId`, `fileId`, `versionId`, `contentId`, `logicalSizeBytes` e o estado final. A substituição de uma binding é atômica: a nova posse é ativada antes da anterior ser liberada.

## `trashPossession`, `restorePossession` e `releasePossession`

- `trashPossession`: move uma posse de workspace para `TRASHED`, preserva cota e define `purgeAfter` pela configuração.
- `restorePossession`: retorna à situação ativa antes de `purgeAfter`.
- `releasePossession`: remove a propriedade de forma irreversível, atualiza consumo e agenda retenção de versões ou objetos sem referências.

Os comandos recusam posses `SYSTEM_MANAGED` pela API genérica de drive; somente o recurso responsável pode liberá-las.

## `authorizePrivateRead`

Recebe a identidade autenticada e exatamente uma referência de posse ou binding. Autoriza somente o proprietário ou um contexto futuro explicitamente concedido. Retorna metadados técnicos e um fluxo de bytes privado; não cria URL pública e não revela backend, chave ou caminho físico.

## Metadados de versão

`storeVersionMetadata` recebe uma posse autorizada, chave, valor JSON e a origem `EXTRACTED` ou `DECLARED`. O valor é associado à versão atual da posse e não cria catálogo, galeria ou interface de arquivos.

## Derivadas reservadas

`reserveVersionDerivative` valida a versão-fonte, o conteúdo derivado e a chave técnica antes de reservar ou atualizar a relação em `file_versionDerivative`. `FileStorageV1` não cria nem entrega thumbnails nesta fase; a relação não cria posse, versão de usuário, listagem ou acesso público.

## Reconciliação privada

O job agendado percorre somente a árvore técnica de objetos configurada. Ele marca referências de catálogo sem bytes físicos e escritas incompletas antigas como `ORPHANED`, e exclui objetos físicos sem registro somente após `FILE_ORPHAN_RETENTION_DAYS`. Não infere proprietário, finalidade ou autorização a partir de um caminho físico.

## Compactação reversível

As regras de `FILE_COMPRESSION_RULES` selecionam conteúdo por MIME type e/ou extensão. A ingestão e o reprocessamento promovem `GZIP` somente se os bytes comprimidos forem menores; caso contrário mantêm `IDENTITY`. A hash e o tamanho lógicos nunca mudam. Ao trocar uma representação ativa, a anterior passa a `RETIRED` e permanece até a retenção técnica aplicável; a promoção valida a hash da representação física antes de reutilizá-la.

## Erros estáveis

| Código | Situação |
| --- | --- |
| `FILE_OWNER_INVALID` | Proprietário ausente ou ambíguo. |
| `FILE_POSSESSION_NOT_FOUND` | Posse inexistente ou inacessível. |
| `FILE_OPERATION_NOT_ALLOWED` | Operação incompatível com a área ou finalidade. |
| `FILE_STORAGE_UNAVAILABLE` | Não há backend ativo para escrita ou leitura. |
| `FILE_CONTENT_INTEGRITY_FAILED` | Hash, tamanho ou promoção física não confere. |
| `FILE_IDEMPOTENCY_CONFLICT` | Mesma chave usada com conteúdo ou intenção diferente. |
