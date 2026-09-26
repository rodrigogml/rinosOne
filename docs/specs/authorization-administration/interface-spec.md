# Interface Specification: Authorization Administration

## Interface Coverage

| Surface ID | Surface Type | Actors | Coverage | Notes |
| --- | --- | --- | --- | --- |
| SURF-WEB-ADMIN | WEB | Administrador autorizado | FULL | Administração, consulta efetiva, explain e auditoria. |

## Interaction Inventory

| Interaction ID | Surface ID | Surface Type | Change Type | Name | Purpose |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-ADMIN-001 | SURF-WEB-ADMIN | WEB | NEW | Segurança do tenant | Gerir acesso e investigar decisão com a API protegida. |

## Interaction Details

### INT-WEB-ADMIN-001 — Segurança do tenant

**Surface**: SURF-WEB-ADMIN
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Administrar roles, grupos, memberships, grants, restrictions, acesso efetivo, explain e auditoria no tenant permitido.
**Actors and Permissions**: Administrador com permission de segurança aplicável; API valida cada ação.
**Entry and Navigation**: Item Segurança do tenant; seções Roles, Grupos, Acessos e Auditoria; voltar retorna ao tenant.
**Content and Data**: DTOs camelCase de `administration-api.md`, sem fatores de terceiros fora do escopo.
**Actions and Behavior**: Criar/alterar/remover, consultar explain/effective access e filtrar auditoria; UI atualiza capabilities após sucesso.
**Validation and Feedback**: Form preserva valores; bloqueio do último admin mostra erro seguro e não altera estado.
**Responsive/Adaptive Behavior**: Abas tornam-se seletor em mobile; ações são menu contextual.
**Accessibility**: Landmarks, headings, foco em diálogo/retorno, teclado e anúncios de sucesso/erro.
**Localization**: i18n para mensagens; datas seguem locale/timezone do workspace.
**Components and Design System**: Navegação, tabela, formulário, diálogo e alertas existentes.
**Integration and Contracts**: `administration-api.md`; UI não decide escopo ou permission.
**Telemetry**: Eventos agregados de operação, sem dados de auditoria ou sujeito.
**Wireframe Requirement**: REQUIRED
**Wireframe**: Embedded

```text
Segurança do tenant: Roles | Grupos | Acessos | Auditoria
Lista/consulta                                  ação contextual protegida
```

| State | Behavior and exit |
| --- | --- |
| initial | Carrega contexto autorizado. |
| loading | Mostra placeholders e desabilita escrita. |
| empty | Informa ausência de registros no contexto. |
| ready | Exibe dados e ações permitidas. |
| processing | Impede duplo envio. |
| success | Atualiza dados/capabilities e anuncia resultado. |
| validation-error | Associa erro seguro ao campo ou ação. |
| remote-error | Preserva dados e permite repetir. |
| offline | Não envia mutação; informa indisponibilidade. |
| access-denied | Remove controles e retorna ao tenant seguro. |
| partial-stale | Reconsulta após mutação/revogação. |

## Traceability

| Interaction ID | User Story | Functional Requirement | Contract | Verification |
| --- | --- | --- | --- | --- |
| INT-WEB-ADMIN-001 | US-AA-001 a 004 | FR-AA-001 a 010 | `administration-api.md` | Tasks 3.1 a 3.3, API, a11y e E2E |

## Validation Summary

Validar API real, teclado, leitor de tela, desktop/mobile, erro de último administrador e revogação na próxima operação.
