# Plano de Implementação: Performance de Autorização

## Resumo

Escalar decisões e listagens sem alterar a semântica de segurança. Cache é um acelerador descartável, vinculado à versão de política do contexto, e a fonte persistente continua autoridade após perda ou falha de cache.

## Contexto Técnico

Laravel/PHP, MySQL core, cache configurado por ambiente, jobs/telemetria existentes e API JSON. Depende de todas as features que alteram política: fundação, restrictions, resource authorization e políticas avançadas.

## Arquitetura das Superfícies de Interação

**Aplicabilidade de Interface Design:** N/A — não introduz tela ou fluxo humano; melhora chamadas e listagens já existentes.

| Surface ID | Cobertura | Tecnologia | Notas |
| --- | --- | --- | --- |
| API-HTTP-V1 | FULL | Laravel/JSON | Check individual, lote e listagens protegidas. |
| SURF-WEB-ACCESS | PARTIAL | Vue 3 | Consome respostas existentes, sem painel de cache. |

## Constitution Check

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Cache só é introduzido após a correção da fonte de verdade. |
| II. API e domínio independentes | PASS | Cache fica atrás da porta de decisão. |
| III–IV. Segurança e dados | PASS | Default deny, invalidação e métricas sem segredos. |
| V. Verificabilidade | PASS | Benchmark e testes de equivalência obrigatórios. |
| VI. Identidades e referências | PASS | Versões e chaves de contexto usam IDs BIGINT, sem FK core→tenant. |

## Desenho da Arquitetura

1. Um `PolicyVersionResolver` produz versão por sujeito e contexto de autorização.
2. Toda alteração em membership, role, grupo, restriction, relation, política, delegação, aprovação, SoD, identidade de serviço ou sincronização incrementa/invalida a versão afetada na transação.
3. A chave de cache inclui sujeito, permission, esfera, tenant, referência de recurso quando houver e versão; ausência/falha de cache chama o resolvedor persistente.
4. Lote e listagem usam resoluções agregadas do domínio, nunca `check` em loop por registro.
5. Métricas agregadas separam deny normal de erro interno e proíbem labels de alta cardinalidade.

## Observabilidade

`AuthorizationMetrics` registra somente contadores estáveis no cache configurado: allow/deny, acerto/erro de cache, duração acumulada e quantidade de decisões em lote. `failure:count` é incrementado apenas quando a resolução lança erro interno; uma negação esperada permanece em `decision:deny`. Nenhuma chave de métrica contém ID, tenant, permission ou referência de recurso.

## Estrutura do Projeto

```text
app/Services/Authorization/Performance/
app/Infrastructure/Authorization/Cache/
app/Infrastructure/Authorization/Observability/
config/authorization.php
tests/Performance/Authorization/
tests/Feature/Authorization/
docs/specs/authorization-performance/
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Cache/DB | snake_case | integração e teste de invalidação | serviço de performance |
| API | camelCase | contrato e testes de lote | `contracts/decision-performance.md` |
| Métricas | nomes estáveis | teste de cardinalidade | adaptador de observabilidade |

## Validação Planejada

- Resultado com e sem cache é equivalente para allow, deny, restriction e relation.
- A próxima operação após cada tipo de mudança usa versão nova.
- Benchmark compara check, lote, grupos, resource listing e invalidação.
- Logs e métricas não contêm identificadores ou labels proibidos.
