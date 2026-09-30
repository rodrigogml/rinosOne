# API Checklist: Interface unificada de acessos e permissões

**Purpose**: Validar que os contratos contextuais de autorização são claros, seguros e compatíveis com a API versionada existente.
**Created**: 2026-09-28
**Feature**: [spec.md](../spec.md)

## Contexto e contratos

- [x] CHK001 - As três esferas possuem prefixo de rota explícito e a API proíbe selecionar escopo pelo corpo de uma escrita? [Segurança, Contract §Convenções e §Famílias de contexto] {auto} — `TENANT`, `PERSONAL` e `PLATFORM` são derivados exclusivamente da rota.
- [x] CHK002 - Requests, responses, case style e formato de identificadores estão definidos para os endpoints novos? [Completude, Contract §Convenções, §Consultas contextuais novas e §Compartilhamento] {auto} — JSON camelCase e BIGINT positivo são convenções explícitas.
- [x] CHK003 - Rotas de tenant existentes mantêm compatibilidade durante a transição para sujeitos contextuais? [Compatibilidade, Contract §Consultas contextuais novas e §Comandos de papel e grupo] {auto} — `effective-access/users` e `explain/users` permanecem previstos.
- [x] CHK004 - Consultas paginadas declaram filtros e não exigem carregar o grafo inteiro de autorização? [Escalabilidade, Contract §Consultas contextuais novas; Plan §Contexto técnico] {auto} — sujeitos e papéis são paginados e o plano proíbe grafo completo inicial.

## Erros, concorrência e observabilidade

- [x] CHK005 - Falhas de autorização, ausência contextual, validação, conflito e versão obsoleta possuem semântica HTTP e envelope seguro definidos? [Clareza, Contract §Convenções e §Cache e concorrência] {auto} — `403`, `404`, `409`, `412` e `422` estão definidos.
- [x] CHK006 - Mutação de acesso define como a interface reconsulta projeções e invalida decisão de autorização? [Consistência, Spec FR-API-016; Contract §Cache e concorrência; Plan §Desenho técnico] {auto} — a versão de política é invalidada e a projeção contextual é recarregada.
- [x] CHK007 - O contrato evita retorno de credenciais, detalhe de auditoria ou fatores confidenciais em listas, contexto e telemetria? [Proteção de dados, Contract §Controles avançados; Interface detalhes `Telemetry`] {auto} — credenciais são somente de emissão única e dados de auditoria são projetados.
- [x] CHK008 - A estratégia de idempotência/concorrência para comandos de acesso é inequívoca para a entrega? [Clareza, Contract §Cache e concorrência; Plan §Convenções de borda] {auto} — versão contextual protege alteração concorrente; comandos continuam sob os serviços transacionais existentes.

## Limites da feature

- [x] CHK009 - Rate limiting específico, timeout de dependência externa e tracing distribuído foram corretamente tratados como não aplicáveis a este contrato interno autenticado? [Escopo, Plan §Contexto técnico; Contract §Convenções] {auto} — a feature não cria integração externa, token público ou serviço distribuído; políticas globais existentes permanecem aplicáveis.
- [x] CHK010 - A ausência de OpenAPI/JSON Schema formal não produz duas fontes concorrentes de verdade? [Consistência, Plan §Convenções de borda] {auto} — o Markdown contratual, testes de feature e parsers TypeScript são a fonte verificável desta fase.

## Notes

- Itens `{auto}` foram resolvidos com evidência citada.
- Não há itens `{humano}` ou gaps de requisitos abertos neste domínio.
