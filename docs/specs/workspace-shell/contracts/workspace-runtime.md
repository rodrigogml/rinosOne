# Contrato Interno: Runtime da Área de Trabalho

Este contrato é interno à interface web. Ele não cria endpoint, tabela ou payload público.

## Catálogo de destinos

Cada módulo aprovado registra um destino com:

| Propriedade | Obrigatória | Contrato |
| --- | --- | --- |
| `id` | sim | Estável, único e independente do texto apresentado. |
| `scope` | sim | `personal` ou `tenant`. |
| `category` | sim | Categoria existente no catálogo de navegação. |
| `titleKey` | sim | Chave localizada nos quatro idiomas. |
| `icon` | sim | Ícone SVG com significado estável. |
| `instancePolicy` | sim | `single` ou `multiple`; o padrão é `single`. |
| `createSurface` | sim | Cria uma superfície com título, ícone e conteúdo. |

O shell filtra `tenant` sem contexto de organização e nunca usa o nome, id ou estado de uma organização como substituto de `scope`.

## Contrato de superfície

Uma superfície expõe ao shell:

| Capacidade | Contrato |
| --- | --- |
| Identidade | `id` único por instância e `destinationId` de origem. |
| Foco | recebe sinal de ativação e desativação. |
| Fechamento | pode informar se há alterações pendentes e recebe confirmação ou cancelamento. |
| Invalidação contextual | recebe encerramento seguro quando a organização muda ou deixa de estar disponível. |
| Sobreposições | solicita diálogos da área de trabalho e notificações pelo runtime, sem montar camadas globais próprias. A superfície hospeda sua própria pilha efêmera de diálogos locais; somente o topo é interativo, a pilha é preservada durante trocas de foco e é descartada ao fechar sua própria instância. |

## Comandos do runtime

| Comando | Resultado esperado |
| --- | --- |
| Abrir destino | Cria, ou foca, a superfície conforme sua política de instância. |
| Ativar superfície | Torna-a exclusiva no palco e atualiza a barra de tarefas. |
| Solicitar fechamento | Fecha diretamente ou abre confirmação se houver alterações pendentes. |
| Limpar contexto de organização | Fecha somente superfícies contextuais da aba atual. |
| Abrir diálogo da área de trabalho | Empilha a interação, bloqueia a área abaixo da topbar e movimenta o foco para o topo. |
| Enfileirar notificação | Apresenta feedback em ordem, sem disputa visual. |
