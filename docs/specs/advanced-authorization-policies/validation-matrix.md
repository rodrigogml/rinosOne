# Matriz de Validação — Políticas Avançadas de Autorização

Esta matriz liga requisitos do escopo às evidências executáveis. Ela não substitui os testes: indica onde a regressão é detectada.

| Requisito | Evidência principal |
| --- | --- |
| FR-AAP-001 a 004 | `PolicyDefinitionValidatorTest`, `AuthorizationPolicyServiceTest`, `AuthorizationPolicyEvaluationTest` |
| FR-AAP-005 a 008 | `AuthorizationDelegationPersistenceTest`, `AdvancedAuthorizationAdministrationApiTest` |
| FR-AAP-009 a 011 | `AuthorizationAccessRequestTest`, `AdvancedAuthorizationAdministrationApiTest`, `AuthorizationAdministrationSurface.spec.ts` |
| FR-AAP-012 | `AuthorizationSeparationRuleTest` e prova de lock concorrente MySQL registrada em `tasks.md` |
| FR-AAP-013 | `AuthorizationServiceIdentityTest`, `ServiceIdentityAuthorizationApiTest` |
| FR-AAP-017 a 019 | `AuthorizationPermissionImplicationTest`, `AuthorizationGroupHierarchyTest`, `AuthorizationResourceRestrictionTest` |
| SC-AAP-001 | `AuthorizationPolicyEvaluationTest` valida evento de auditoria sem contexto sensível |
| SC-AAP-002 | Testes de restriction, delegação, acesso temporário e expiração acima |
| SC-AAP-003 | `TenantMembershipServiceTest`, `AuthorizationAdministrationApiTest` |
| SC-AAP-004 | `PolicyDefinitionValidatorTest`, `AuthorizationPolicyEvaluationTest` |
| INT-WEB-POLICY-001/002 | `AuthorizationAdministrationSurface.spec.ts`, `AdvancedAuthorizationAdministrationApiTest` |

## Dados sensíveis, logs e retenção

- `AuthorizationAuditLogger` remove chaves que contenham `password`, `secret`, `token`, `credential` ou `authorization` antes de persistir snapshots.
- Eventos de política registram somente o identificador, versão, permission e resultado; nunca a definição, os atributos avaliados ou credenciais.
- Credenciais técnicas persistem apenas hash; o valor da API key é emitido uma vez e não é enviado à auditoria.
- A retenção de auditoria usa configuração própria, com padrão de 90 dias, coberta por `AuthorizationAuditRetentionTest` e `AuthorizationConfigurationTest`.
- Métricas de decisão são agregadas e sem rótulos sensíveis, cobertas por `AuthorizationMetricsTest`.
