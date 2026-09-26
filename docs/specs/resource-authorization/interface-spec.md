# Interface Specification: Resource Authorization

## Interface Coverage

| Surface ID | Surface Type | Actors | Coverage | Notes |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Pessoa autenticada | PARTIAL | Lista e ações de recurso usam decisão real; editor de relações é adiado. |

## Interaction Inventory

| Interaction ID | Surface ID | Surface Type | Change Type | Name | Purpose |
| --- | --- | --- | --- | --- | --- |
| INT-WEB-RESOURCE-001 | SURF-WEB-ACCESS | WEB | MODIFIED | Lista autorizada | Mostrar somente recursos retornados pela API protegida. |
| INT-WEB-RESOURCE-002 | SURF-WEB-ACCESS | WEB | MODIFIED | Ação de recurso | Executar action apenas após decisão por recurso. |

## Interaction Details

### INT-WEB-RESOURCE-001 — Lista autorizada

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Renderizar somente itens autorizados, sem contagem de itens ocultos.
**Actors and Permissions**: Pessoa autenticada; a API decide acesso e relation.
**Entry and Navigation**: Lista existente do workspace; filtros e paginação preservados.
**Content and Data**: Itens e ações vêm do contrato `resource-decision.md`.
**Actions and Behavior**: Abrir item ou ação disponível; a UI não calcula grants.
**Validation and Feedback**: Resposta inválida não atualiza store; erro seguro oferece repetir.
**Responsive/Adaptive Behavior**: Metadados empilham em mobile e ações vão para menu contextual.
**Accessibility**: Landmark de lista, títulos acessíveis, foco por item e anúncios de carregamento/erro.
**Localization**: Mensagens via i18n; datas/moedas seguem locale do workspace.
**Components and Design System**: Lista, placeholders, alertas e menu existentes.
**Integration and Contracts**: API protegida e `resource-decision.md`.
**Telemetry**: Resultado agregado de carregamento; sem ID de recurso.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — alteração de conteúdo/estado em lista existente.

| State | Behavior and exit |
| --- | --- |
| initial | Inicia consulta autorizada. |
| loading | Exibe placeholders sem inferir itens. |
| empty | Informa ausência de itens acessíveis. |
| ready | Mostra somente payload autorizado. |
| processing | N/A — leitura. |
| success | N/A — `ready` é o sucesso. |
| validation-error | Payload inválido mantém conteúdo anterior. |
| remote-error | Mensagem segura e ação repetir. |
| offline | Mantém conteúdo anterior identificado como indisponível. |
| access-denied | Retorna ao workspace sem revelar recurso. |
| partial-stale | Reconsulta após mutation de relation. |

### INT-WEB-RESOURCE-002 — Ação de recurso

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Exercer action somente no recurso avaliado.
**Actors and Permissions**: Pessoa autenticada com decisão allow atual.
**Entry and Navigation**: Detalhe ou menu contextual da lista autorizada.
**Content and Data**: Referência tipada enviada pela API, nunca inferida localmente.
**Actions and Behavior**: Chama endpoint protegido; negação remove ação/item atual.
**Validation and Feedback**: Falha não confirma existência de recurso externo.
**Responsive/Adaptive Behavior**: Cabeçalho em desktop e menu contextual em mobile.
**Accessibility**: Foco retorna ao heading seguro após negação; teclado opera ação.
**Localization**: Mensagens seguras via i18n.
**Components and Design System**: Botões, menu, alerta e feedback existentes.
**Integration and Contracts**: `resource-decision.md` e endpoint de domínio protegido.
**Telemetry**: Apenas resultado agregado da action.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — sem alteração estrutural.

| State | Behavior and exit |
| --- | --- |
| initial | Aguarda ação. |
| loading | Resolve detalhe autorizado. |
| empty | N/A — detalhe não existe sem lista. |
| ready | Ação permitida é habilitada. |
| processing | Desabilita repetição durante chamada. |
| success | Atualiza detalhe/lista pelo payload real. |
| validation-error | Mostra erro de entrada sem alterar recurso. |
| remote-error | Mostra erro seguro e permite repetir. |
| offline | Impede envio e mantém estado local. |
| access-denied | Remove ação e retorna a destino seguro. |
| partial-stale | Revalida após revogação/mudança. |

## Traceability

| Interaction ID | User Story | Functional Requirement | Contract | Verification |
| --- | --- | --- | --- | --- |
| INT-WEB-RESOURCE-001 | US-RA-004 | FR-RA-010 a 013 | `resource-decision.md` | Task 3.1, componente e E2E |
| INT-WEB-RESOURCE-002 | US-RA-001/002 | FR-RA-001 a 009 | `resource-decision.md` | Task 3.2, E2E e a11y |

## Validation Summary

API real, parser TypeScript, teclado, leitor de tela e viewport desktop/mobile são obrigatórios nas tasks 3.1 e 3.2.
