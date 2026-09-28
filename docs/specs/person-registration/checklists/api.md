# API Checklist: Cadastro de Pessoas por Organização

**Purpose**: verificar qualidade dos contratos, erros, autorização, paginação, mutações e observabilidade da API de Pessoas.  
**Created**: 2026-09-28  
**Feature**: [people-api.md](../contracts/people-api.md)

## Contratos e autorização

- [x] CHK-API-001 - Todos os endpoints de listagem, criação, detalhe, atualização, duplicação, ciclo de vida e exclusão estão definidos sob contexto explícito de organização? [Completude, Contract §Operações] {auto}
- [x] CHK-API-002 - Cada operação declara autenticação, contexto organizacional e permissão prevista, sem aceitar escopo implícito? [Segurança, Contract introdução; Contract §Operações] {auto}
- [x] CHK-API-003 - O agregado de criação/atualização explicita campos mínimos, coleções opcionais e regras principais de endereço e documento? [Clareza, Contract §Criar ou atualizar Pessoa] {auto}
- [x] CHK-API-004 - O detalhe explica a projeção de relacionamento de entrada como apresentação, sem representar vínculo persistido duplicado? [Consistência, Contract §Detalhe de Pessoa; Data model §personRelationship] {auto}

## Erros, privacidade e exclusão

- [x] CHK-API-005 - Os erros de validação, acesso, inexistência, documento, relacionamento e exclusão possuem status e códigos coerentes? [Cobertura, Contract §Erros comuns] {auto}
- [x] CHK-API-006 - A exclusão diferencia uso conhecido de impedimento de integridade não classificado e proíbe detalhe técnico na resposta ao usuário? [Segurança, Contract §Exclusão física; Plan §Fluxos técnicos principais] {auto}
- [x] CHK-API-007 - Documentos na projeção resumida são explicitamente mascarados para apresentação? [Proteção de dados, Contract §Pessoa resumida] {auto}

## Paginação, idempotência e concorrência

- [x] CHK-API-008 - A listagem define paginação por página, padrão 50, máximo 200, ordenação estável e resposta de paginação? [Clareza, Contract §Políticas gerais de operação; Spec CS-005] {auto}
- [x] CHK-API-009 - Criação, duplicação e exclusão definem `Idempotency-Key` UUID v4 com resultado repetível por 24 horas configuráveis? [Confiabilidade, Contract §Políticas gerais de operação] {auto}
- [x] CHK-API-010 - Atualização define versão de leitura e resposta de conflito que materializa o estado `partial-stale` da interface? [Concorrência, Contract §Políticas gerais de operação; Interface INT-WEB-PEOPLE-002] {auto}

## Limites e observabilidade

- [x] CHK-API-011 - Limite geral configurável de corpo JSON de 1 MiB está definido e a feature não inventa teto específico de coleção sem evidência? [Proteção operacional, Contract §Políticas gerais de operação; Contract §Criar ou atualizar Pessoa] {auto}
- [x] CHK-API-012 - Limitação geral configurável de 120 requisições autenticadas por minuto e resposta de excesso segura estão definidas para buscas? [Abuso e privacidade, Contract §Políticas gerais de operação] {auto}
- [x] CHK-API-013 - Métricas seguras de latência, volume, erro, limitação e conflito por operação são exigidas sem dados pessoais? [Observabilidade, Plan §Políticas gerais de API e concorrência] {auto}

## Notes

- Não há lacunas abertas neste domínio.
