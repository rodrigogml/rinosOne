# Especificação da Feature: Performance de Autorização

**Feature**: `authorization-performance`  
**Criada em**: 2026-09-26  
**Status**: Draft

## Direção de Produto

O rinosOne deve preservar a semântica de segurança da autorização quando o número de tenants, pessoas, grupos, recursos e relações crescer. Cache é acelerador, nunca fonte de verdade; uma perda completa de cache não pode permitir ou negar acesso de forma diferente da fonte persistente.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| API protegida | Other | Pessoa autenticada e consumidor autorizado | FULL | Mantém decisões e listagens autorizadas previsíveis sob carga. | Controles operacionais externos de cache. |
| Workspace autenticado | Web responsiva | Pessoa autenticada | PARTIAL | Recebe capabilities e listagens atualizadas após alterações de acesso. | Painel operacional de performance. |

## Cenários de Usuário e Testes

### User Story 1 - Revogar acesso mesmo com cache (Prioridade: P1)

Como responsável pela segurança, quero que uma alteração de acesso invalide decisões anteriores para que a próxima operação reflita a política atual.

**Teste independente**: uma pessoa possui acesso obtido por cache, perde role, group, restriction ou relation e tem a próxima operação negada.

### User Story 2 - Avaliar várias ações eficientemente (Prioridade: P1)

Como pessoa usuária, quero que uma tela ou fluxo que exige várias capabilities receba decisões consistentes sem demora perceptível nem concessões divergentes.

**Teste independente**: uma solicitação em lote retorna o mesmo resultado das decisões individuais equivalentes e não altera a política.

### User Story 3 - Listar recursos autorizados em escala (Prioridade: P1)

Como pessoa usuária, quero que listas protegidas retornem somente recursos acessíveis sem uma verificação independente por item.

**Teste independente**: uma listagem grande preserva o mesmo subconjunto autorizado do controle de referência e não executa decisão por registro como estratégia principal.

### User Story 4 - Observar o comportamento do motor (Prioridade: P2)

Como equipe operacional, quero distinguir negações esperadas de falhas internas e acompanhar custo, cache e tamanho de lote sem registrar dados sensíveis.

**Teste independente**: métricas e logs agregados mostram decisões, duração, cache e lote sem usar identificador de recurso ou permission de alta cardinalidade como rótulo.

### Casos de Borda

- Falha ou perda de cache mantém decisão correta pela fonte persistente.
- Toda chave ou versão de decisão considera tenant quando aplicável.
- Uma alteração concorrente de política não permite reutilizar decisão anterior na operação seguinte.
- Métricas não expõem permission, recurso ou identificador de pessoa em rótulos de alta cardinalidade.

## Requisitos

### Requisitos Funcionais

- **FR-AP-001**: O sistema DEVE tratar cache de autorização como acelerador descartável e manter correção após sua perda.
- **FR-AP-002**: O sistema DEVE associar decisões armazenadas a uma versão de política do contexto aplicável, incluindo tenant quando houver.
- **FR-AP-003**: O sistema DEVE atualizar a versão de política após mudança relevante de role, group, membership, restriction, relation, política condicional, delegação, aprovação, separação de funções ou identidade de serviço.
- **FR-AP-004**: A próxima operação protegida após mudança relevante DEVE usar política atual e não decisão anterior inválida.
- **FR-AP-005**: O sistema DEVE suportar decisão em lote consistente com decisões individuais equivalentes.
- **FR-AP-006**: O sistema DEVE filtrar recursos autorizados por consulta ou processamento agregado, sem loop de decisão individual como estratégia principal.
- **FR-AP-007**: O sistema DEVE manter semântica de default deny e precedência de restriction idênticas com ou sem cache.
- **FR-AP-008**: O sistema DEVE medir decisões, allows, denies, duração, acertos e falhas de cache e tamanho de lote com dados agregados seguros.
- **FR-AP-009**: O sistema DEVE diferenciar negação esperada de falha interna de resolução nos logs e observabilidade.
- **FR-AP-010**: O sistema DEVE manter benchmarks representativos de check simples, lote, grupos, grupos aninhados, relações, listagem e invalidação.
- **FR-AP-011**: O sistema NÃO DEVE persistir chaveiro completo de permissions na sessão nem permitir que cache substitua validações de tenant, membership ou recurso.
- **FR-AP-012**: A feature DEVE usar invalidação orientada a alteração de política; não depende de agendamento, token externo ou rotação de chaves.

### Entidades Principais

- **Versão de política**: identificação lógica da política atual de um contexto de autorização.
- **Entrada de decisão em cache**: resultado reutilizável associado a pessoa, escopo, contexto e versão atual.
- **Avaliação em lote**: conjunto de decisões calculadas de forma agregada.
- **Métrica de autorização**: medida agregada de decisão e custo sem dado sensível de alta cardinalidade.

## Critérios de Sucesso

- **SC-AP-001**: 100% dos testes de revogação com cache confirmam negação na próxima operação.
- **SC-AP-002**: 100% dos cenários de lote retornam o mesmo resultado das decisões individuais de referência.
- **SC-AP-003**: 100% das listagens protegidas em benchmark preservam o subconjunto autorizado sem estratégia de decisão por item.
- **SC-AP-004**: 100% das métricas testadas evitam labels com identificador de recurso ou permission de alta cardinalidade.
