# Especificação da Feature: Administração de Autorização

**Feature**: `authorization-administration`  
**Criada em**: 2026-09-26  
**Status**: Draft

## Direção de Produto

Administrar acesso é uma operação protegida pelo próprio sistema de autorização. O rinosOne deve permitir que administradores autorizados gerenciem memberships, groups, roles, assignments e restrictions dentro da esfera e tenant corretos, compreendam o acesso efetivo e consultem a auditoria sem receber acesso fora de sua competência.

Nesta primeira administração, `tenant.administrator` pode gerenciar integralmente a segurança de seu tenant. Delegação parcial de competência, hierarquias administrativas e roles não delegáveis pertencem à feature `advanced-authorization-policies`.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Administração de segurança | Web responsiva | Administrador PLATFORM ou TENANT autorizado | FULL | Gerencia acesso, consulta auditoria e explica decisões na esfera permitida. | Delegação avançada e workflows de aprovação. |
| API protegida da plataforma | Other | Interface web e consumidor futuro autorizado | FULL | Executa operações administrativas e fornece acesso efetivo e auditoria com escopo protegido. | API pública para terceiros. |
| Workspace autenticado | Web responsiva | Pessoa autenticada | DEFERRED | Nenhuma alteração de superfície nesta entrega; o backend já reavalia autorização na próxima operação. | Atualização visual de capabilities fora da área de administração. |

## Cenários de Usuário e Testes

### User Story 1 - Gerenciar roles e grants do tenant (Prioridade: P1)

Como administrador de tenant, quero criar roles próprias e atribuí-las a pessoas e grupos para administrar acesso organizacional.

**Teste independente**: um administrador cria role TENANT compatível, associa permissions válidas, atribui-a a membro ativo e confirma que a próxima ação da pessoa reflete a concessão.

**Cenários de aceitação**:

1. **Dado** que administro o Tenant A, **quando** crio ou altero uma role TENANT compatível, **então** ela não pode conter permission de PLATFORM ou de outro tenant.
2. **Dado** que atribuo role a pessoa com membership ativa no Tenant A, **quando** ela executa action concedida, **então** a decisão reflete a atribuição.
3. **Dado** que tento atribuir role a pessoa sem membership ativa ou de outro tenant, **quando** concluo a operação, **então** ela é recusada.

### User Story 2 - Organizar grupos e memberships (Prioridade: P1)

Como administrador de tenant, quero gerenciar grupos e seus membros para aplicar acessos coletivos com segurança.

**Teste independente**: um administrador cria grupo, adiciona membro elegível, atribui role ao grupo e remove membro, observando a concessão e a revogação correspondentes.

**Cenários de aceitação**:

1. **Dado** que crio grupo no Tenant A, **quando** adiciono pessoa com membership ativa no mesmo tenant, **então** ela se torna elegível aos grants do grupo.
2. **Dado** que removo pessoa de grupo, **quando** ela executa nova operação antes concedida somente pelo grupo, **então** o acesso é negado.
3. **Dado** que uma mudança de grupo criaria ciclo, **quando** tento concluí-la, **então** a alteração é recusada sem modificar a hierarquia válida.

### User Story 3 - Bloquear e restaurar acesso (Prioridade: P1)

Como administrador de tenant, quero administrar restrictions para bloquear pontualmente acesso excessivo e restaurá-lo quando necessário.

**Teste independente**: um administrador cria restriction para pessoa ou grupo e vê a próxima operação bloqueada; ao removê-la, o grant aplicável volta a funcionar.

### User Story 4 - Explicar acesso efetivo e auditar alterações (Prioridade: P2)

Como administrador, quero entender por que uma pessoa possui ou não uma action e consultar mudanças de segurança para investigar acessos.

**Teste independente**: a consulta de acesso efetivo identifica membership, groups, roles, grants, restrictions e relações aplicáveis, enquanto a auditoria mostra a alteração correspondente sem dados sensíveis.

### Casos de Borda

- Operação administrativa sem permission de segurança aplicável é negada pelo mesmo motor de autorização.
- Administração TENANT não permite consultar ou alterar dados de outro tenant.
- A remoção do último `tenant.administrator` é recusada e comunicada sem alteração parcial.
- Roles e groups de sistema protegidos não podem ser alterados por administração comum.
- Consultas de explicação e auditoria não revelam dados de sujeito, recurso ou tenant fora do escopo autorizado.

## Requisitos

### Requisitos Funcionais

- **FR-AA-001**: O sistema DEVE proteger toda operação administrativa pelas permissions de segurança aplicáveis à esfera e tenant.
- **FR-AA-002**: O sistema DEVE permitir administrar memberships, groups, roles, assignments e restrictions somente no contexto autorizado.
- **FR-AA-003**: O sistema DEVE validar compatibilidade de esfera, tenant, estado e sujeito em toda escrita administrativa.
- **FR-AA-004**: O sistema DEVE impedir alteração de roles e permissions gerenciadas pelo sistema por administrador comum.
- **FR-AA-005**: O sistema DEVE preservar a regra do último `tenant.administrator` em toda operação administrativa relevante.
- **FR-AA-006**: O sistema DEVE disponibilizar consulta de acesso efetivo que diferencie membership, groups, roles diretas, roles herdadas, permissions, restrictions e relations aplicáveis.
- **FR-AA-007**: O sistema DEVE disponibilizar explicação de decisão permitida ou negada sem expor informação fora do escopo administrativo da pessoa solicitante.
- **FR-AA-008**: O sistema DEVE disponibilizar consulta de auditoria protegida, filtrável por contexto e alvo, sem permitir alteração dos eventos.
- **FR-AA-009**: O sistema DEVE refletir alterações administrativas na próxima operação protegida e nas capabilities reavaliadas.
- **FR-AA-010**: O sistema DEVE auditar toda operação administrativa concluída ou recusada por invariantes de segurança relevantes.
- **FR-AA-011**: O sistema NÃO DEVE introduzir delegação parcial, workflow de aprovação, gestão de administrador PLATFORM pela web ou edição de dados de auditoria nesta feature.
- **FR-AA-012**: A feature NÃO DEVE depender de agendamento, credencial externa, rotação de chaves ou persistência de decisão na sessão.

### Entidades Principais

- **Operação administrativa de segurança**: alteração ou consulta que administra acesso e exige permission específica.
- **Acesso efetivo**: composição atual de vínculos e regras que levam à decisão de uma pessoa.
- **Explicação de decisão**: resposta administrativa segura que informa os fatores aplicáveis de allow ou deny.
- **Consulta de auditoria**: visão protegida dos eventos imutáveis de segurança no contexto autorizado.

## Critérios de Sucesso

- **SC-AA-001**: 100% dos cenários automatizados de escrita administrativa fora de escopo são negados sem alteração parcial.
- **SC-AA-002**: 100% dos cenários automatizados de acesso efetivo distinguem grants diretos, grupos, restrictions e relations aplicáveis.
- **SC-AA-003**: 100% das alterações administrativas concluídas ou recusadas por invariante produzem auditoria segura quando aplicável.
- **SC-AA-004**: 100% dos testes de remoção do último administrador comprovam que o tenant preserva ao menos um administrador elegível.
