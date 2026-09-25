# Modelo de Estado: Área de Trabalho da Aplicação

Nenhuma entidade persistida, tabela ou migration é criada por esta feature. Os conceitos abaixo existem somente em memória dentro de uma aba autenticada.

## Entidade: `workspaceState`

| Campo | Tipo lógico | Restrições | Observações |
| --- | --- | --- | --- |
| `menuCollapsed` | boolean | obrigatório | Preferência efêmera da área atual. |
| `openSurfaceIds` | lista ordenada | obrigatória | Ordem de abertura das superfícies presentes na barra de tarefas. |
| `activeSurfaceId` | identificador nulo | nulo quando vazia | Única superfície apresentada no palco. |
| `dialogStack` | pilha | obrigatória | Somente o diálogo no topo recebe interação. |
| `notificationQueue` | fila | obrigatória | Feedbacks aguardando apresentação. |

## Entidade: `workspaceDestination`

| Campo | Tipo lógico | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | identificador estável | único | Identifica a capacidade no catálogo interno. |
| `scope` | pessoal ou organização | obrigatório | Controla visibilidade conforme o contexto selecionado. |
| `category` | identificador | obrigatório | Agrupa o destino no menu e no mega menu. |
| `title` | conteúdo localizado | obrigatório | Nome de menu, superfície e tarefa. |
| `icon` | referência visual | obrigatória | Mantém reconhecimento consistente. |
| `instancePolicy` | única ou múltipla | obrigatório | Única é o padrão do catálogo. |
| `surfaceFactory` | referência de apresentação | obrigatória | Cria a superfície ao abrir o destino. |

## Entidade: `workspaceSurface`

| Campo | Tipo lógico | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | identificador único por instância | único na aba | Distingue instâncias múltiplas. |
| `destinationId` | referência | obrigatória | Aponta para o destino de origem. |
| `scopeSnapshot` | pessoal ou organização | obrigatório | Evita reutilizar superfície contextual após mudança de organização. |
| `title` | conteúdo localizado | obrigatório | Pode acrescentar contexto da instância futuramente. |
| `dirty` | boolean | obrigatório, padrão falso | Solicita confirmação antes de fechar ou substituir. |
| `status` | aberta, ativa, encerrando ou indisponível | obrigatório | Orienta apresentação e transições. |

### Transições de Superfície

```text
aberta -> ativa
ativa -> aberta
aberta|ativa -> encerrando -> removida
aberta|ativa -> indisponível -> removida
```

Uma mudança de contexto de organização remove somente superfícies cujo `scopeSnapshot` seja de organização. Superfícies pessoais permanecem abertas.

## Entidade: `workspaceDialog`

| Campo | Tipo lógico | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | identificador único | único na pilha | Permite remover o diálogo correto. |
| `kind` | informação, atenção, erro ou confirmação | obrigatório | Define semântica e criticidade. |
| `closePolicy` | dispensável ou explícita | obrigatória | Define Escape e clique externo. |
| `originSurfaceId` | identificador nulo | opcional | Define o retorno de foco. |

## Entidade: `workspaceWindowDialog`

| Campo | Tipo lógico | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | identificador único | único na pilha da instância | Permite dispensar somente o diálogo visível. |
| `title` | conteúdo localizado | obrigatório | Identifica a operação local em curso. |
| `description` | conteúdo localizado | obrigatório | Explica o bloqueio sem depender da camada global. |

Cada `workspaceSurface` mantém sua própria pilha efêmera de `workspaceWindowDialog`. Somente o item no topo é interativo; a pilha permanece quando outra janela se torna ativa e é descartada somente quando sua própria superfície é fechada.

## Entidade: `workspaceNotification`

| Campo | Tipo lógico | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | identificador único | único na fila | Evita remoção de feedback incorreto. |
| `kind` | informação, sucesso, atenção ou erro | obrigatório | Define anúncio e aparência semântica. |
| `message` | conteúdo localizado | obrigatório | Texto seguro e compreensível. |
| `duration` | intervalo | obrigatório | Pode ser persistente somente quando a pessoa precisa agir. |
