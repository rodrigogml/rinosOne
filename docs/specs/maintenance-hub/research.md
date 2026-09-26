# Pesquisa Técnica — Central de Manutenções

## Decisão 1: Hub por integrações explícitas

**Decisão**: o hub conhecerá cada manutenção por uma integração específica no próprio módulo de manutenção. Não haverá registro dinâmico, catálogo de rotinas persistido nem contrato genérico imposto às rotinas.

**Racional**: preserva regras próprias de cada rotina e a direção de dependência definida pelo briefing: o hub conhece a rotina, a rotina não conhece o hub.

**Alternativas consideradas**: criar uma interface plugável ou uma tabela de definição de rotinas. Foram rejeitadas por anteciparem uma abstração comum não autorizada.

## Decisão 2: Separar execução técnica de ação administrativa

**Decisão**: histórico técnico e auditoria administrativa serão persistidos separadamente, com retenções independentes configuráveis e padrão de 90 dias.

**Racional**: uma execução programada não tem necessariamente um administrador como causa; uma ação administrativa deve ser auditável mesmo se a rotina a recusar.

**Alternativas consideradas**: uma única tabela de logs. Rejeitada porque mistura autoria administrativa, resultado técnico e políticas de retenção diferentes.

## Decisão 3: Agenda delegada e específica

**Decisão**: a central terá pontos explícitos de agenda para cada rotina que os exigir. A integração de instituições financeiras será diária; rotinas futuras por evento ou sem agenda não receberão uma agenda genérica.

**Racional**: a central coordena visibilidade e disparo, mas não estabelece regras de agenda, singleton, retentativas ou instâncias.

**Alternativas consideradas**: agenda universal configurável por dados. Rejeitada por conflitar com a autonomia de cada rotina.

## Decisão 4: Concorrência da rotina de instituições financeiras

**Decisão**: a atualização do catálogo financeiro é singleton. Seu motor compartilha o mesmo bloqueio para disparos manuais e diários e recusa qualquer solicitação concorrente, sem fila de espera ou segunda instância. A recusa manual é auditável.

**Racional**: a rotina atualiza um catálogo global único; instâncias paralelas não agregam valor e dificultam a operação.

**Alternativas consideradas**: executar em paralelo ou enfileirar a solicitação foram rejeitadas pelo requisito explícito de recusa de execuções simultâneas.

## Decisão 5: Autorização declarada por rotina

**Decisão**: cada integração documenta as actions autorizáveis e usa a porta única da fundação de permissões. A rotina financeira declara as permissions PLATFORM `platform.maintenance.financial-institution.read` e `platform.maintenance.financial-institution.synchronize`; a role de sistema `platform.maintenance.financial-institution.operator` as agrupa. A role `platform.administrator` também as recebe, sem qualquer atribuição automática a usuários.

**Racional**: a fundação de permissões agora está disponível. O agrupamento próprio da rotina preserva delegação administrativa específica, enquanto a role de administrador da Plataforma mantém sua capacidade global.

**Alternativas consideradas**: conceder acesso a todos os usuários autenticados ou depender somente da role global de administrador. Rejeitadas por não atender ao requisito de permissions próprias por rotina e ação.

## Decisão 6: Dados seguros e retenção mínima

**Decisão**: histórico e auditoria armazenarão somente contexto seguro necessário à operação; mensagens técnicas detalhadas, payloads e segredos não serão persistidos ou exibidos por padrão.

**Racional**: atende à auditoria sem converter o hub em repositório de dados sensíveis.
