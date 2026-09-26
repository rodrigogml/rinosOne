# Revisão para Integração de Novas Rotinas

Este documento registra a fronteira aprovada para a Central de Manutenções: uma rotina existente não passa a ser exibida, disparável ou agendável pelo hub somente por já possuir um comando ou agenda no sistema.

> [!IMPORTANT]
> Cada item abaixo requer requisitos próprios, uma integração explícita no hub e validação de segurança antes de qualquer alteração. O hub conhece a rotina após sua implementação; a rotina não deve passar a depender do hub.

## Critérios obrigatórios por rotina

Antes de integrar uma rotina, documentar e aprovar:

1. proprietário, objetivo e dados que podem aparecer para administradores;
2. modelo de disparo — agenda, evento, fila ou operação manual — e se uma ação manual é permitida;
3. concorrência, idempotência, tempo limite, instância única e comportamento de recusa;
4. permissões próprias por ação, conciliadas com o modelo definitivo de autorização;
5. histórico técnico, auditoria administrativa, retenções e dados que nunca podem ser expostos;
6. impacto de falha, telemetria segura, recuperação e cenários de teste.

## Rotinas existentes que continuam fora do hub

| Rotina atual | Ponto de execução | Motivo para não integrar agora | Revisão mínima necessária |
| --- | --- | --- | --- |
| Limpeza de desafios de autenticação vencidos | `access:purge-expired-challenges` a cada 15 minutos | Trata credenciais e estados de acesso. | Confirmar se nunca admite disparo manual, os dados agregados seguros e a separação da auditoria de segurança. |
| Limpeza de eventos de auditoria de autorização | `authorization:purge-audit-events` diária | Remove evidências de autorização. | Validar exigências legais, retenção própria, autorização de visualização e proibição de operação manual. |
| Limpeza de posses de arquivos expiradas | `PurgeExpiredFilePossessions` | Afeta arquivos e quota. | Determinar impacto de remoção, política de recuperação, escopo seguro de relatórios e concorrência com operações de arquivo. |
| Limpeza de versões retidas de arquivos | `PurgeRetainedFileVersions` | Afeta versões e recuperação. | Definir critérios de elegibilidade, retenção, salvaguardas e se haverá somente consulta. |
| Limpeza de objetos de armazenamento retidos | `PurgeRetainedFileStorageObjects` | Pode remover conteúdo físico. | Revisar risco operacional, reconciliação, bloqueios, relatório seguro e proibir disparo manual até haver requisitos explícitos. |
| Reconciliação de objetos de armazenamento | `ReconcileFileStorageObjects` | Examina divergências entre catálogo e armazenamento. | Definir eventos, reprocessamento, relatórios e conteúdo seguro de inconsistências. |
| Reprocessamento de compressão de arquivos | `ReprocessFileStorageCompression` | Pode alterar representações técnicas. | Confirmar política de instâncias, custo, retry, métricas e parâmetros que podem ser administrados. |

## Rotina já integrada

A sincronização de instituições financeiras é a única rotina integrada nesta fase. Ela possui agenda diária própria, singleton, recusa de solicitações simultâneas, permissões de leitura e sincronização e histórico/auditoria seguros. Essa implementação não estabelece precedente de contrato genérico para os itens acima.

## Como prosseguir

Quando uma das rotinas for priorizada, abrir um SDD individual e concluir os critérios desta revisão antes de alterar `MaintenanceHubService`, a superfície web ou `routes/console.php`.
