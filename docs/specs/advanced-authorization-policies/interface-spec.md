# Interface Specification: Advanced Authorization Policies

## Interface Coverage

| Surface ID | Surface Type | Actors | Coverage | Notes |
| --- | --- | --- | --- | --- |
| SURF-WEB-ADMIN | WEB | Administrador/autorizador elegível | FULL | Políticas, delegações, requests, SoD e integrações. |
| SURF-WEB-ACCESS | WEB | Pessoa autenticada | PARTIAL | Solicita e acompanha acesso temporário. |

## Interaction Inventory

| Interaction ID | Surface ID | Surface Type | Change Type | Name | Purpose |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-POLICY-001 | SURF-WEB-ADMIN | WEB | NEW | Políticas e acessos avançados | Administrar e aprovar sem revelar regras/credenciais. |
| INT-WEB-POLICY-002 | SURF-WEB-ACCESS | WEB | NEW | Solicitar acesso temporário | Solicitar e acompanhar estado seguro. |

## Interaction Details

### INT-WEB-POLICY-001 — Políticas e acessos avançados

**Surface**: SURF-WEB-ADMIN
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Publicar condição/SoD, gerir delegação, service identity/diretório e aprovar request elegível.
**Actors and Permissions**: Administrador ou aprovador independente, confirmado pela API.
**Entry and Navigation**: Seções Políticas, Delegações, Solicitações e Integrações na área Segurança.
**Content and Data**: Modelos/estados de `advanced-policies-api.md`; sem credencial ou grupo externo não permitido.
**Actions and Behavior**: Criar/publicar/revogar, decidir request e consultar resultado seguro.
**Validation and Feedback**: Valida limites/independência; preserva campos não sensíveis no erro.
**Responsive/Adaptive Behavior**: Seções em seletor mobile e editor por passos.
**Accessibility**: Labels/descrições, foco em diálogos, teclado e anúncios dinâmicos.
**Localization**: i18n e período no timezone do contexto.
**Components and Design System**: Formulário estruturado, tabela, diálogo, alerta e stepper existentes.
**Integration and Contracts**: `advanced-policies-api.md`; UI não avalia política.
**Telemetry**: Somente ação/resultado agregado.
**Wireframe Requirement**: REQUIRED
**Wireframe**: Embedded

```text
Segurança: Políticas | Delegações | Solicitações | Integrações
Lista filtrada                                    detalhe e validação segura
```

| State | Behavior and exit |
| --- | --- |
| initial | Carrega contexto permitido. |
| loading | Exibe placeholders. |
| empty | Explica ausência de itens. |
| ready | Mostra dados autorizados. |
| processing | Evita reenvio. |
| success | Atualiza estado e anuncia resultado. |
| validation-error | Mostra erro seguro por campo/ação. |
| remote-error | Permite repetir sem expor regra interna. |
| offline | Não envia alteração. |
| access-denied | Remove ação e retorna seguro. |
| partial-stale | Reconsulta após decisão/sync. |

### INT-WEB-POLICY-002 — Solicitar acesso temporário

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Solicitar elevação elegível e acompanhar pendência/aprovação/expiração.
**Actors and Permissions**: Pessoa autenticada elegível segundo API.
**Entry and Navigation**: Ação Solicitar acesso no workspace; status no contexto atual.
**Content and Data**: Estado seguro da request e vigência, sem cadeia de aprovadores.
**Actions and Behavior**: Enviar/cancelar solicitação quando permitido e atualizar estado real.
**Validation and Feedback**: Erro não confirma regra confidencial.
**Responsive/Adaptive Behavior**: Form compacto em mobile.
**Accessibility**: Labels, foco, teclado e anúncios de status.
**Localization**: i18n e período localizado.
**Components and Design System**: Form, status badge e alertas existentes.
**Integration and Contracts**: `advanced-policies-api.md`.
**Telemetry**: Resultado agregado, sem justificativa sensível.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — formulário simples no workspace existente.

| State | Behavior and exit |
| --- | --- |
| initial | Apresenta elegibilidade conhecida. |
| loading | Carrega status. |
| empty | N/A — sem request é estado inicial. |
| ready | Permite ação elegível. |
| processing | Bloqueia reenvio. |
| success | Exibe status retornado. |
| validation-error | Mantém justificativa não sensível. |
| remote-error | Permite repetir. |
| offline | Não envia. |
| access-denied | Oculta ação sem explicar regra. |
| partial-stale | Reconsulta após decisão/expiração. |

## Traceability

| Interaction ID | User Story | Functional Requirement | Contract | Verification |
| --- | --- | --- | --- | --- |
| INT-WEB-POLICY-001 | US-AP-001/002/004 | FR-AAP-001 a 008/012 a 016 | `advanced-policies-api.md` | Task 4.2, API, a11y e visual |
| INT-WEB-POLICY-002 | US-AP-003 | FR-AAP-009 a 011 | `advanced-policies-api.md` | Task 4.3 e E2E |

## Validation Summary

Validar API real, independência de aprovador, expiração, desktop/mobile e ausência de dados confidenciais.
