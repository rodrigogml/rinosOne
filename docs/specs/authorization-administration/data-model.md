# Modelo de Dados: Administração de Autorização

Não cria entidade de autorização nova: projeta `auth_permission`, `auth_role`, assignments, grupos, memberships, `auth_restriction`, relations e `auth_audit_event` já definidos. Consultas administrativas usam DTOs sem alterar as tabelas de origem.

## Projeções

- **EffectiveAccess**: sujeito, contexto, vínculos ativos, permissions e restrictions/relation aplicáveis.
- **DecisionExplanation**: resultado, motivo seguro e fatores visíveis ao administrador.
- **AuditPage**: eventos filtráveis por contexto/alvo, sem campos secretos ou edição.
