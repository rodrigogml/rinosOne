# Pesquisa técnica: Interface unificada de acessos e permissões

## Decision 1: Evoluir a superfície administrativa existente

**Decision**: Evoluir `resources/js/authorization/AuthorizationAdministrationSurface.vue` para uma composição contextual única, extraindo cliente, tipos e subpainéis conforme necessário. A entrada existente `tenant.authorization-administration` permanece válida.

**Rationale**: A aplicação já possui Área de Trabalho, componentes de formulário, alertas, diálogos, i18n e uma superfície de segurança. Reutilizá-los preserva navegação, reduz duplicação e permite substituir gradualmente formulários baseados em IDs por seletores legíveis.

**Alternatives considered**: Criar uma SPA paralela de administração foi rejeitado por duplicar shell, controle de contexto e componentes. Reescrever a superfície de uma vez foi rejeitado por ampliar risco e impedir entrega incremental.

## Decision 2: Contexto derivado da navegação, nunca de campo livre

**Decision**: A API terá famílias de rotas explícitas para `TENANT`, `PERSONAL` e `PLATFORM`; a camada Vue usará um adaptador contextual comum. Nenhuma escrita aceitará `scope`, `tenantId` ou identificador de workspace no corpo para decidir a esfera.

**Rationale**: A rota e a superfície montada já identificam o contexto de trabalho. Isso impede alteração acidental entre tenants e impede que o cliente tente escolher uma esfera mais ampla.

**Alternatives considered**: Um endpoint genérico com `scope` e `contextId` no payload foi rejeitado por criar uma via de escalonamento no contrato. Manter somente rotas de tenant foi rejeitado porque não atende workspaces pessoais nem a administração central aprovada.

## Decision 3: Projeções de leitura em vez de novo modelo de autorização

**Decision**: A interface consumirá projeções de leitura contextuais compostas das entidades de autorização existentes, com identificadores BIGINT e DTOs camelCase. Apenas dados necessários para decidir ou explicar acesso serão expostos.

**Rationale**: Papéis, permissões, grupos, vínculos, relações de recurso e auditoria já são a fonte de verdade. Uma tabela exclusiva para a tela introduziria duplicação, sincronização e risco de decisão divergente.

**Alternatives considered**: Persistir um “perfil efetivo” de cada pessoa foi rejeitado por desatualização após revogação. Fazer a tela montar todas as relações a partir de endpoints granulares foi rejeitado por aumentar chamadas, enumeração e inconsistência visual.

## Decision 4: Fonte de verdade no backend e parser estrito no frontend

**Decision**: As decisões e invariantes continuam nos serviços Laravel; o frontend recebe capabilities, resumo e ações permitidas como auxílio de usabilidade e valida a forma dos DTOs antes de renderizar.

**Rationale**: A UI pode esconder ações indisponíveis e explicar acesso, mas uma capability visual não é autorização. A mesma mutação precisa continuar protegida contra requests forjados, revogação concorrente e mudança de contexto.

**Alternatives considered**: Autorizar somente pela visibilidade dos botões foi rejeitado por não proteger API. Enviar todo o grafo de autorização ao navegador foi rejeitado por exposição excessiva e performance.

## Decision 5: Compartilhamento como relação de recurso, sem posse individual

**Decision**: O painel de compartilhamento administra relações de recursos do workspace usando a fundação de `auth_resource_relation`; ele exibe o responsável pelo workspace como contexto, sem criar campo ou conceito de proprietário de arquivo ou pasta.

**Rationale**: A regra de negócio aprovada liga a responsabilidade máxima ao workspace pessoal ou do tenant. O compartilhamento é uma capacidade limitada e potencialmente herdada, não transferência de propriedade.

**Alternatives considered**: Usar semântica de posse do sistema operacional foi rejeitado por não corresponder ao workspace virtual. Criar proprietário por item foi rejeitado por contradizer a regra de produto.

## Decision 6: Progressão de complexidade por navegação, não por remoção

**Decision**: Participantes, papéis e compartilhamentos ficam na camada inicial; mecanismos avançados permanecem em uma seção progressiva que reutiliza as operações existentes de políticas, delegações, solicitações, separação de funções e identidades de serviço.

**Rationale**: O administrador cotidiano deve concluir ações frequentes sem ver controles especializados, enquanto administradores experientes mantêm acesso total às capacidades aprovadas.

**Alternatives considered**: Ocultar definitivamente controles avançados foi rejeitado por reduzir funcionalidade. Exibir todos os formulários no primeiro painel foi rejeitado por tornar a interface difícil de aprender e mais propensa a erro.
