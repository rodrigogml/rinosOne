# Modelo de dados — Fundação de armazenamento de arquivos

## Localização e convenções

As tabelas pertencem ao schema global `rinosone`. Elas usam IDs numéricos, colunas `id...`, `createdAt` e `updatedAt`, com InnoDB e chaves estrangeiras explícitas. O prefixo `file_` delimita o contexto de armazenamento.

| Tabela | Responsabilidade |
| --- | --- |
| `file_file` | Linhagem estável do arquivo lógico. |
| `file_fileVersion` | Versões ramificáveis da linhagem, incluindo a versão-pai. |
| `file_fileContent` | Bytes lógicos deduplicados por SHA-256 original. |
| `file_storageBackend` | Catálogo de backends físicos, sem caminhos ou segredos. |
| `file_storageObject` | Representação física de um conteúdo em um backend. |
| `file_filePossession` | Posse e estado de uma linhagem para um usuário ou tenant. |
| `file_systemBinding` | Vínculo de um recurso do sistema a uma posse, como avatar atual. |
| `file_ownerUsage` | Totais lógicos por proprietário, sem enforcement nesta fase. |
| `file_versionMetadata` | Metadados extraídos ou declarados por versão. |
| `file_versionDerivative` | Registro de representação derivada futura de uma versão, sem expô-la como posse. |
| `file_workspaceFolder` | Pasta lógica de um único workspace de usuário ou tenant. |

## Entidades principais

### `file_file`

`id`, `fileUuid` (único), `createdAt`, `updatedAt`. Não guarda nome de exibição nem proprietário; ambos pertencem à posse.

### `file_fileVersion`

`id`, `idFile`, `idParentFileVersion` nulo, `idFileContent`, `versionNumber`, `createdAt`. Índice único em `(idFile, versionNumber)`. A relação pai preserva a origem real de cada ramificação.

### `file_fileContent`

`id`, `logicalSha256` (único), `logicalSizeBytes`, `detectedMimeType`, `declaredExtension`, `createdAt`. A hash é dos bytes antes de qualquer codificação técnica.

### `file_storageBackend`

`id`, `backendKey` (único), `state` (`ACTIVE`, `READ_ONLY`, `INACTIVE`), `createdAt`, `updatedAt`. A chave é resolvida para um disco/caminho somente pela configuração do ambiente.

### `file_storageObject`

`id`, `idFileContent`, `idStorageBackend`, `storedSha256`, `storageKey`, `encoding` (`IDENTITY`, `GZIP`), `storedSizeBytes`, `state` (`WRITING`, `ACTIVE`, `RETIRED`, `ORPHANED`), `retentionUntil`, `createdAt`, `updatedAt`.

`storageKey` segue árvore por hash, não contém identificador do proprietário nem da versão. Índice único em `(idStorageBackend, storedSha256, encoding)`.

### `file_filePossession`

`id`, `idFile`, `idCurrentFileVersion`, `idUser` nulo, `idTenant` nulo, `storageArea` (`WORKSPACE`, `SYSTEM_MANAGED`), `purpose` nulo, `displayName`, `state` (`ACTIVE`, `TRASHED`, `RELEASED`), `logicalSizeBytes`, `trashedAt`, `purgeAfter`, `releasedAt`, `createdAt`, `updatedAt`.

Exatamente um de `idUser` e `idTenant` é obrigatório. Uma posse em `SYSTEM_MANAGED` não é listada pelo futuro drive; `purpose` define seu ciclo de vida. A lixeira mantém a posse e seu consumo lógico até a liberação.

### `file_workspaceFolder`

`id`, `idParentFolder` nulo, `idUser` nulo, `idTenant` nulo, `displayName`, `state` (`ACTIVE`, `TRASHED`), `trashedAt`, `purgeAfter`, `createdAt`, `updatedAt`. Exatamente um de `idUser` e `idTenant` é obrigatório; a raiz do workspace é implícita (`idParentFolder` nulo). A mesma estrutura atende workspaces pessoais e organizacionais, mas uma pasta e seus filhos nunca atravessam o proprietário do workspace. A remoção transiciona recursivamente a árvore e as posses nela alocadas à lixeira; o restauro recompõe o conjunto enquanto os prazos de retenção permitirem.

### `file_systemBinding`

`id`, `idUser`, `bindingKey`, `idFilePossession` nulo, `createdAt`, `updatedAt`. Índice único em `(idUser, bindingKey)`. Para o avatar, `bindingKey` é `USER_PROFILE_AVATAR`; há no máximo uma posse ativa vinculada por usuário.

### `file_ownerUsage`

`id`, `idUser` nulo, `idTenant` nulo, `workspaceBytes`, `systemManagedBytes`, `trashBytes`, `totalBytes`, `createdAt`, `updatedAt`. Exatamente um dono. Os totais são atualizados na transação de criação, transferência de estado e liberação de posse.

### `file_versionMetadata`

`id`, `idFileVersion`, `metadataKey`, `metadataValue` JSON, `source` (`EXTRACTED`, `DECLARED`), `createdAt`. Índice em `(idFileVersion, metadataKey)`. Suporta data de captura, equipamento, localização, orientação e propriedades futuras sem tornar a tabela de versão excessivamente larga.

### `file_versionDerivative`

`id`, `idSourceFileVersion`, `idFileContent`, `derivativeKind`, `derivativeKey`, `state` (`PENDING`, `ACTIVE`, `FAILED`, `RETIRED`), `createdAt`, `updatedAt`. Índice único em `(idSourceFileVersion, derivativeKind, derivativeKey)`.

Uma derivada é produzida a partir de uma versão, mas seu resultado também é conteúdo deduplicável e usa a mesma cadeia privada de objeto físico. Ela não é uma versão do arquivo lógico, não recebe posse e não é listada ao usuário. Nesta fase, a tabela apenas reserva a relação necessária para thumbnails ou outras representações futuras; nenhum gerador ou endpoint de derivadas será entregue.

## Invariantes

- Um conteúdo lógico pode ter muitas versões, mas somente um registro por SHA-256.
- Um objeto físico pode servir a muitas versões e nunca identifica uma posse pelo caminho.
- Uma posse tem uma versão atual; versões anteriores permanecem retidas até que nenhuma referência e a política permitam limpeza.
- Uma derivada referencia uma versão de origem e conteúdo de resultado, sem alterar a linhagem nem a versão atual de qualquer posse.
- A liberação de uma posse não remove bytes se outra posse ou versão ainda os referenciar.
- Objetos físicos só podem ser removidos após retenção técnica suficiente para o período de restauração de backup configurado.
