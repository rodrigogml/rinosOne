# API Checklist: Fundação de Tenants

**Purpose**: validar a qualidade dos contratos de criação, listagem, contexto e disponibilidade de tenants.
**Created**: 2026-09-24
**Feature**: [tenant-context.md](../contracts/tenant-context.md)

## Contratos e Autorização

- [x] CHK001 - Todos os fluxos de interface possuem endpoint, método, autenticação e resultado esperado definidos? [Completude, Contracts §Listar tenants a §Alterar disponibilidade] {auto}
- [x] CHK002 - A versão da API e o case style de payload são consistentes com a fronteira existente? [Consistência, Plan §Convenções de Borda; Contracts introdução] {auto}
- [x] CHK003 - A criação exige chave de idempotência e define o resultado da repetição da mesma intenção? [Clareza, Contracts §Criar tenant; Spec §FR-TEN-017] {auto}
- [x] CHK004 - O contexto não é gravado na sessão e operações futuras recebem tenant explícito com revalidação? [Segurança, Contracts §Validar e iniciar contexto; §Convenção para módulos futuros] {auto}

## Falhas e Observabilidade

- [x] CHK005 - A seleção não revela se a negação decorre de inexistência, associação ou indisponibilidade? [Segurança, Contracts §Validar e iniciar contexto; Spec §FR-TEN-009] {auto}
- [x] CHK006 - Estados de criação e disponibilidade possuem códigos HTTP e códigos de erro definidos? [Completude, Contracts §Criar tenant; §Alterar disponibilidade] {auto}
- [x] CHK007 - O envelope JSON comum para erros, incluindo campos seguros exibíveis pela interface, está definido para todos os códigos do contrato? [Clareza, Contracts §Envelope de erro] {auto}
- [x] CHK008 - Eventos de criação, contexto e negação excluem nome, chave de intenção, schema e detalhes internos? [Observabilidade, Spec §FR-TEN-018; Interface §INT-WEB-001 a §INT-WEB-003] {auto}

## Escopo e Evolução

- [x] CHK009 - O contrato reserva prefixo contextual para módulos futuros sem introduzir endpoints de módulos nesta fase? [Escopo, Contracts §Convenção para módulos futuros; Spec §FR-TEN-019] {auto}
- [x] CHK010 - Paginação e filtros são N/A nesta primeira lista, cujo escopo é somente os tenants vinculados ao usuário atual? [Escopo, Contracts §Listar tenants disponíveis; Spec §FR-TEN-019] {auto}

## Notes

- Todos os itens deste domínio foram resolvidos por evidência de contrato.
