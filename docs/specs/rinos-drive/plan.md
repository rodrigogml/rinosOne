# Plano técnico — Rinos Drive unificado

## Resumo

O Rinos Drive evolui de duas entradas dependentes de contexto para uma ferramenta global de instância única. Ela exibe, sob demanda, o catálogo seguro de Meu Drive, Drives Work efetivamente acessíveis e Compartilhados comigo. A fundação de arquivos, deduplicação, quota, retenção e armazenamento privado permanece única; esta evolução só cria projeções, relações diretas de arquivo e operações lógicas entre workspaces.

Transferir entre drives não desloca bytes. Uma cópia cria a posse e os vínculos lógicos adequados no destino, reutilizando o conteúdo deduplicado. Um movimento somente libera a origem depois da cópia íntegra. Reservas persistentes impedem alterações concorrentes nos ramos envolvidos.

## Contexto técnico

- **Backend**: PHP 8.2+, Laravel 12, MySQL 9, serviços de domínio, filas persistidas e scheduler.
- **Web**: Vue 3, TypeScript, Pinia, Axios, Vue I18n, SPA responsiva na Área de Trabalho.
- **Dados e arquivos**: schema global `rinosone`; conteúdo em backend privado configurado; `file_fileContent` continua a autoridade dos bytes deduplicados.
- **Padrões existentes reutilizados**: `DriveWorkspaceTarget`, serviços de projeção/comando/upload/exportação, `AuthorizationResourceAdapter`, relações contextualizadas, jobs idempotentes e locks transacionais `lockForUpdate()`.
- **Restrições**: API JSON versionada, default deny, IDs numéricos, payload sem caminhos/hash/chave física/URL pública, nenhuma quota bloqueante, nenhum preview ou editor nesta fase.
- **Interface Design Applicability**: REQUIRED — a feature altera ferramenta desktop/mobile, árvore, seleção, drag-and-drop, diálogos, estados de background e acessibilidade.

## Constitution Check

*GATE: aprovado antes do desenho e rechecado após este plano.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | Amplia somente o Drive aprovado; edição online, links externos, preview e gestão visual de compartilhamento continuam fora do escopo. |
| II. Fronteira API e domínio independente da interface | PASS | Catálogo, autorização direta, transferência e reservas pertencem a serviços/API; Vue somente consome projeções. |
| III. Identidade e acesso seguros por padrão | PASS | Catálogo é filtrado no servidor; relações de arquivo são read-only; operação revalida origem e destino antes do commit. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Transferência guarda somente referências, estado, lease e correlação; prazos operacionais são configuração de ambiente. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Contratos, dados, interface, quickstart, testes de concorrência e E2E evoluem juntos. |
| VI. Identidades numéricas e referências unidirecionais | PASS | Novas tabelas globais usam BIGINT; referências continuam no schema global e não criam FK core → tenant. |

## Arquitetura

### Catálogo e alvos seguros

1. `DriveCatalogService` resolve o principal autenticado e retorna somente raízes disponíveis: Meu Drive, cada tenant com acesso efetivo e Compartilhados comigo.
2. A raiz Work é incluída somente para administrador ativo ou quando existir relação efetiva a pelo menos uma pasta/arquivo daquele tenant; membership isolada não expõe nome, contagem ou uso do Drive.
3. Cada referência transporta um alvo completo `{ scope, tenantId?, kind, localId? }`; ids locais nunca são aceitos sem escopo e tenant correspondentes.
4. As rotas por alvo existentes permanecem como borda de operação. O resolvedor deixa de depender da organização ativa na interface e valida tenant, membership e autorização efetiva por requisição.
5. A árvore e as coleções são lazy: o catálogo não carrega conteúdo; cada painel busca somente sua raiz, pasta ou lixeira ativa.
6. Revogação ou perda de membership remove somente a raiz/ramo afetado do cache local; o painel mostra estado seguro e não conserva metadados não autorizados.

### Compartilhados comigo e autorização direta de arquivo

- Registrar `WorkspaceFileAuthorizationResourceAdapter` para os tipos `personal.file` e `tenant.file`.
- Relação direta de arquivo aceita somente `READ`; o destinatário pode listar o atalho, consultar metadados seguros, baixar, exportar ou copiar para destino editável. Não pode renomear, mover, lixar, restaurar ou substituir a posse de origem.
- `SharedWithMeProjectionService` combina relações diretas de pasta e arquivo concedidas ao principal. Ele não transforma ancestrais em breadcrumbs nem enumera irmãos; cada item aponta para o alvo de origem e recebe revalidação em toda operação.
- Uma concessão direta redundante não duplica um item já visível pelo mesmo caminho concedido. A projeção prefere a entrada de pasta quando o arquivo é descendente de uma pasta diretamente acessível.

### Painéis paralelos e comandos locais

- `DriveExplorer` torna-se a casca única com um ou dois `DriveNavigationPane` independentes. Cada painel possui alvo, localização, árvore lazy, seleção, modo de visualização, carregamento, erro e painel de detalhes próprios.
- Um botão próximo de Atualizar alterna o segundo painel. No telefone, a comparação acontece em drawer/modal de troca, sem duas colunas simultâneas nem scroll horizontal do canvas.
- Arrastar seleção de um painel para outro sempre abre `DriveTransferDialog`. O diálogo apresenta Cancelar, Copiar e Mover; pré-seleciona Mover no mesmo drive e Copiar entre drives.
- A seleção só aceita itens de uma mesma origem em cada comando. A interface mostra origem, destino e categoria de operação, nunca caminho físico ou dados de outro drive.

### Transferência lógica e reservas

1. `DriveTransferService::request()` valida origem, destino, modo e seleção; cria `file_workspaceTransfer` e suas reservas na mesma transação, com idempotency key e correlação.
2. A reserva materializa o conjunto de raízes protegidas de origem e destino. Toda mutação de pasta, posse, upload, lixeira, restauro ou limpeza consulta `DriveTransferReservationService` antes de executar e responde `DRIVE_TRANSFER_IN_PROGRESS` se intersectar um ramo reservado.
3. `ProcessWorkspaceTransfer` executa em fila, atualiza progresso seguro e renova o lease. Fechar painel ou janela não altera o job.
4. Na confirmação, o job bloqueia registros lógicos relevantes em ordem estável, revalida autorização e cria as posses/pastas de destino. Conteúdo físico é referenciado pela fundação, nunca copiado no backend.
5. Em modo `MOVE`, depois de a cópia lógica estar íntegra, a origem é liberada pela transição de lifecycle existente. Qualquer falha ou revogação antes do commit descarta o estágio de destino e mantém a origem.
6. `RecoverExpiredWorkspaceTransfers` renova/reagenda operações recuperáveis ou marca falha e libera reservas após lease vencido. Rotina, lease, heartbeat e máximo de tentativas são configuráveis por ambiente.

### Lixeira, quota e auditoria

- Cada drive preserva lixeira e `ownerUsage` próprios. Compartilhados comigo não possui quota, lixeira ou retenção própria.
- Administrador ou membro com edição efetiva no Drive Work pode recuperar itens daquele workspace, mesmo se outro membro os enviou à lixeira; o evento de auditoria registra o ator de remoção/restauro.
- Transferências inter-drive não transferem quota, política de retenção, permissões, relações ou ownership sem criar a nova posse correspondente no destino.
- Eventos técnicos registram somente categoria, alvo, operação, estado, contagem/faixa e correlation id; não incluem nome, caminho, hash ou conteúdo.

## Modelo de dados e contratos

- [Modelo de dados](data-model.md) evolui com recursos de arquivo autorizáveis, concessões diretas, operação de transferência e reservas com lease.
- [Contrato HTTP](contracts/drive-workspace-api.md) adiciona catálogo, compartilhados, operação/status/cancelamento de transferência e respostas seguras de bloqueio.
- [Pesquisa](research.md) registra catálogo, item compartilhado, transferência lógica, reserva e recuperação.
- [Quickstart](quickstart.md) cobre catálogo, compartilhado, dois painéis, cópia, movimento, revogação, concorrência e recuperação.

## Estrutura do projeto

```text
app/
├── Domain/FileStorage/Drive/                    # value objects de alvo, item, transferência e reserva
├── Http/Controllers/Api/V1/Drive/               # catálogo, navegação e comandos
├── Http/Requests/Drive/                         # validação de transferência e comandos
├── Infrastructure/FileStorage/Authorization/    # adapters de folder e file resource
├── Jobs/FileStorage/                            # processar, recuperar e limpar transferências/exportações
├── Models/FileStorage/                          # transferências, reservas, posses e pastas
└── Services/FileStorage/Drive/                  # catálogo, compartilhados, projeção, comando e transferência
config/file-storage.php                          # lease, retry e manutenção de transferências
database/migrations/core/                        # relações de arquivo, transferências e reservas
resources/js/
├── drive/                                       # explorer, painéis, drag/drop, diálogo e cliente HTTP
└── workspace/                                   # ferramenta global do Rinos Drive
tests/
├── Feature/                                     # API, isolamento, transferência, reserva e recuperação
├── Unit/                                        # alvos, autorização, locks e state machine
├── js/drive/                                    # painéis, seleção, acessibilidade e parsers
└── e2e/                                         # catálogo e transferência desktop/mobile
docs/specs/rinos-drive/                          # artefatos desta feature
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Tabelas e colunas | `file_*`, `id...`, camelCase | migrations, FKs e índices | `database/migrations/core/` |
| Value objects e serviços | PascalCase/camelCase | PHPUnit | `app/Domain/FileStorage/Drive/`, `app/Services/FileStorage/Drive/` |
| Payload JSON | camelCase | Form Requests, testes HTTP e contrato | `contracts/drive-workspace-api.md` |
| Tipos e parsers web | camelCase | TypeScript, parser e Vitest | `resources/js/drive/` |
| Rotas e parâmetros | kebab-case; ids numéricos | router e requests | `routes/api/authenticated.php` |

**Camada de mapeamento (DB ↔ DTO)**: serviços do Drive projetam catálogo, localização, item compartilhado e transferência; controllers não expõem modelos, storage keys ou relações internas.

**Validação de schema**: backend valida entrada; frontend interpreta toda resposta com parser TypeScript antes de alterar estado do painel. Respostas de progresso não contêm itens não autorizados.

**Envelope de erro**: `DRIVE_ACCESS_DENIED`, `DRIVE_TRANSFER_IN_PROGRESS`, `DRIVE_TRANSFER_INVALID`, `DRIVE_TRANSFER_CANCELLED`, `DRIVE_TRANSFER_FAILED` e `DRIVE_TRANSFER_STALE` seguem o envelope seguro, sem revelar identificação de ramo, titular ou operação de terceiro.

## Validação planejada

- Testes de catálogo para isolamento por membership, administrador, relação de pasta, relação direta de arquivo, revogação e ausência de enumeração.
- Testes de persistência para operação, reserva, ordenação estável de locks, lease, recuperação, retry e liberação idempotente.
- Testes HTTP para cada origem/destino, cópia/movimento, seleção mista recusada, read-only compartilhado, lixeira por workspace e revalidação antes do commit.
- Testes de interface para painel duplo, drag/drop, opção padrão, diálogo, progresso após fechar janela, foco, leitor de tela, i18n e mobile sem scroll horizontal.
- E2E autenticado para catálogo real, duas raízes, cópia/movimento e revogação; roundtrip valida o payload real contra o contrato.

## Complexity Tracking

Nenhuma violação da Constituição foi identificada. As reservas persistentes e o job de transferência são complexidade necessária para preservar integridade de árvores, autorização e lifecycle quando uma operação lógica atravessa workspaces.
