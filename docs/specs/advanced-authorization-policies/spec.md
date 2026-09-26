# Spec — Políticas Avançadas de Autorização

**Status:** Rascunho  
**Dependências:** `authorization-foundation`, `authorization-restrictions`, `resource-authorization`, `authorization-administration` e `authorization-performance`  
**Identificadores:** todas as entidades persistidas usam `BIGINT UNSIGNED`.

## Objetivo

Permitir que autorizações sejam condicionadas a atributos do pedido, limites de negócio, separação de funções, delegação controlada, prazo e identidades não humanas, sem alterar a regra estrutural já definida: uma restrição aplicável sempre nega; uma concessão aplicável permite; e, na ausência de ambos, o acesso é negado.

Esta é uma capacidade complementar. Papéis, permissões, grupos, escopos, relações com recursos e restrições continuam sendo a base da decisão; as políticas avançadas apenas qualificam uma concessão que já seria elegível.

## Escopo

Inclui políticas condicionais e reutilizáveis aplicáveis aos escopos `PLATFORM`, `PERSONAL` e `TENANT`; delegação administrativa limitada; acesso temporário e sujeito a aprovação; separação de funções; identidades de serviço; e sincronização de grupos por diretório corporativo. A primeira integração corporativa adotará SCIM 2.0 por adaptador, preservando o domínio independente do fornecedor.

Não inclui a criação de novas permissões de produto, nem substitui fluxos funcionais de aprovação próprios de cada módulo. Quando uma política exigir aprovação, ela decide somente sobre a ativação temporária de um acesso; a aprovação de uma transação de negócio continua sob responsabilidade do módulo correspondente.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Administração de segurança | Web responsiva | Administrador autorizado e aprovador elegível | FULL | Publica políticas, administra delegações, trata solicitações e consulta integrações. | Painel de fornecedor específico de diretório. |
| API protegida da plataforma | Other | Interface web, job de integração e consumidor autorizado | FULL | Avalia contexto e opera políticas, delegações, solicitações e sincronização protegidas. | API pública para parceiros. |
| Workspace autenticado | Web responsiva | Pessoa autenticada | PARTIAL | Solicita acesso temporário e recebe resultado seguro da aprovação. | Editor de políticas ou administração corporativa. |

## Histórias de usuário e requisitos

### US-AP-001 — Autorizar conforme contexto de negócio

Como administrador autorizado, quero vincular condições a uma concessão para limitar quando ela pode ser exercida, sem criar uma permissão nova para cada combinação de regra.

**Critérios de aceitação**

1. Uma política pode avaliar atributos confiáveis da decisão, incluindo escopo, tenant, relação com recurso, valor monetário, unidade organizacional, horário, localização lógica e atributos do sujeito.
2. A política só pode permitir uma ação quando já existe concessão válida para a mesma permissão, escopo e recurso, se houver recurso.
3. Condição não satisfeita resulta em negação sem revelar o valor, regra ou atributo que a causou ao solicitante sem privilégio administrativo.

**Requisitos funcionais**

- **FR-AAP-001:** O sistema DEVE manter um catálogo versionado de tipos de condição suportados, com semântica explícita, validação de parâmetros e operador definido.
- **FR-AAP-002:** O sistema DEVE permitir compor condições por conjunção e disjunção, com precedência declarada e limites para impedir políticas excessivamente complexas.
- **FR-AAP-003:** O sistema DEVE avaliar apenas atributos obtidos de fonte confiável ou calculados pelo servidor; atributos informados livremente pelo cliente não PODEM ampliar autorização.
- **FR-AAP-004:** O sistema DEVE registrar na auditoria o identificador e a versão da política aplicada, o resultado e uma explicação administrativa segura da condição satisfeita ou não satisfeita.

### US-AP-002 — Delegar administração sem ampliar privilégios

Como administrador, quero delegar uma capacidade por prazo e limites definidos para que responsáveis locais trabalhem sem receber mais poder do que o delegador possui.

**Critérios de aceitação**

1. O delegador só pode delegar uma permissão que esteja efetivamente autorizado a exercer e delegar no mesmo escopo.
2. A delegação pode ser limitada por tenant, recurso, período, valor máximo e unidade organizacional.
3. A revogação da concessão de origem, da delegação ou do acesso do delegador elimina imediatamente o acesso delegado.

**Requisitos funcionais**

- **FR-AAP-005:** O sistema DEVE distinguir permissões exercíveis de permissões delegáveis e negar delegação quando a permissão, papel ou concessão de origem não for delegável.
- **FR-AAP-006:** O sistema DEVE preservar a cadeia de origem de cada delegação, impedir ciclos e limitar sua profundidade configurável.
- **FR-AAP-007:** O sistema NÃO DEVE permitir delegar os papéis protegidos do sistema, incluindo `tenant.administrator`, nem permissões classificadas como não delegáveis.
- **FR-AAP-008:** O sistema DEVE exigir privilégio administrativo específico para criar, alterar, revogar ou consultar uma delegação e auditar cada evento com delegador, destinatário, limites e origem.

### US-AP-003 — Aplicar prazo, aprovação e separação de funções

Como responsável de segurança, quero exigir controles adicionais para acessos sensíveis e temporários, evitando concentração indevida de poder.

**Critérios de aceitação**

1. Um acesso temporário expira automaticamente no instante configurado e deixa de produzir decisão favorável sem depender de tarefa manual.
2. Uma concessão que requer aprovação só se torna efetiva após aprovação válida de aprovador elegível e independente.
3. Uma regra de separação de funções impede combinações incompatíveis mesmo quando cada papel isolado seria permitido.

**Requisitos funcionais**

- **FR-AAP-009:** O sistema DEVE permitir configurar uma vigência para concessões avançadas, delegações e elevações temporárias, usando início inclusivo e fim exclusivo.
- **FR-AAP-010:** O sistema DEVE suportar solicitação, aprovação, rejeição, expiração e revogação de acesso temporário, mantendo histórico imutável da decisão.
- **FR-AAP-011:** O sistema DEVE validar que o aprovador não seja o solicitante, o destinatário nem integrante de uma cadeia de delegação que origine o acesso solicitado.
- **FR-AAP-012:** O sistema DEVE permitir declarar pares ou conjuntos incompatíveis de papéis, permissões e funções, aplicando a regra tanto na concessão quanto na decisão de autorização.

### US-AP-004 — Integrar pessoas e identidades de serviço corporativas

Como administrador de integração, quero aplicar as mesmas garantias de autorização a identidades não humanas e grupos sincronizados, com responsabilidade e rastreabilidade claras.

**Critérios de aceitação**

1. Uma identidade de serviço recebe somente concessões explicitamente atribuídas, com credencial, escopo e prazo próprios.
2. Alteração vinda de diretório externo respeita o mapeamento aprovado e não remove o último administrador direto de um tenant.
3. Falha, atraso ou remoção de vínculo externo não permite ampliar acesso; a política de continuidade configurada é auditável.

**Requisitos funcionais**

- **FR-AAP-013:** O sistema DEVE representar identidades de serviço separadamente de usuários humanos, associando proprietário responsável, finalidade, credenciais permitidas, escopo e vigência.
- **FR-AAP-014:** O sistema DEVE suportar sincronização de grupos de diretório corporativo por adaptador aprovado, com mapeamento explícito para grupos internos e trilha de auditoria de cada alteração recebida.
- **FR-AAP-015:** O sistema DEVE aplicar princípio de falha segura a integrações de identidade: dados ausentes, inválidos, expirados ou não verificados não PODEM conceder ou manter acesso acima da política de continuidade configurada.
- **FR-AAP-016:** O sistema DEVE manter a regra de proteção do administrador de tenant diante de sincronizações, delegações, aprovações e políticas avançadas: todo tenant precisa conservar ao menos uma associação direta, ativa e elegível ao papel `tenant.administrator`.

## Regras transversais

- A precedência é invariável: restrição aplicável > ausência de concessão válida > política condicional não satisfeita > concessão permitida. Uma política avançada jamais neutraliza uma restrição.
- Políticas não podem ser configuradas para criar acesso implícito; elas apenas reduzem, condicionam ou ativam uma concessão explicitamente existente.
- Todas as mudanças administrativas e decisões de aprovação, delegação, sincronização ou expiração devem gerar evento de auditoria no padrão de retenção configurável, cujo valor padrão é 90 dias.
- A interface de administração deverá fornecer simulação e explicação segura antes da publicação; o desenho detalhado dessa interface pertence à fase de planejamento e design de interface.

## Fora do escopo

- Motor genérico de expressões arbitrárias, scripts ou execução de código fornecido por administradores.
- Aprovação de transações de negócio, assinaturas eletrônicas ou fluxo financeiro dos módulos de domínio.
- Fornecedor de diretório diferente de SCIM 2.0, SSO/protocolo de autenticação e mapeamento específico de atributos de cada fornecedor.
- Substituição de políticas já publicadas sem versionamento, rastreabilidade e validação de compatibilidade.

## Critérios de sucesso mensuráveis

- **SC-AAP-001:** 100% das decisões que envolvam política avançada registram a versão e o resultado da política sem expor atributos sensíveis ao solicitante comum.
- **SC-AAP-002:** Em testes de regressão, nenhuma combinação de delegação, aprovação, sincronização ou condição consegue superar uma restriction aplicável ou preservar acesso após revogação/expiração.
- **SC-AAP-003:** 100% das tentativas de remover a última associação direta e ativa de `tenant.administrator` são bloqueadas, independentemente da origem administrativa ou integrada.
- **SC-AAP-004:** Para cada tipo de condição publicado, existem testes de valor válido, valor ausente, fronteiras temporais e negação por atributo não confiável.
