# Security Checklist: Interface unificada de acessos e permissões

**Purpose**: Validar os requisitos de autorização, proteção de dados e auditoria da interface antes da implementação.
**Created**: 2026-09-28
**Feature**: [spec.md](../spec.md)

## Autorização e isolamento

- [x] CHK001 - O modelo de autorização contextual e a separação entre `PERSONAL`, `TENANT` e `PLATFORM` estão definidos sem seleção livre de esfera? [Completude, Spec §Objetivo e §Limites; Contract §Famílias de contexto] {auto} — rota, contexto ativo e backend determinam a esfera.
- [x] CHK002 - A UI não é tratada como decisão final de segurança e cada leitura/escrita é reavaliada pelo servidor? [Deny-by-default, Spec §Limites; Plan §Desenho técnico; Interface §INT-WEB-ADMIN-001] {auto} — capabilities são somente auxílio de UX.
- [x] CHK003 - Operações capazes de ampliar ou remover acesso possuem confirmação, auditoria e proteção de invariante de último administrador? [Cobertura, Spec FR-API-012, FR-API-013 e FR-API-015; Quickstart §Cenário 2] {auto} — confirmação contextual e resposta `409` são definidos.
- [x] CHK004 - Explicação de acesso e busca de sujeitos evitam enumeração ou vazamento de outro contexto? [Proteção de dados, Spec FR-API-003 e FR-API-009; Contract §Convenções] {auto} — recursos fora de contexto não revelam existência e respostas são projeções mínimas.

## Dados e credenciais

- [x] CHK005 - O requisito de não existir dono individual de arquivo/pasta é consistente no modelo, contrato e interface? [Consistência, Spec FR-API-011; Data Model §Compartilhamento; Interface §Shared Content and Terminology] {auto} — responsabilidade é exclusivamente do workspace.
- [x] CHK006 - Credenciais de integração mantêm emissão única, hash persistido e ausência de segredo em auditoria, cache e telemetria? [Least privilege, Spec §Limites; Contract §Controles avançados] {auto} — a interface apenas encaminha o mecanismo avançado existente e não relê segredo.
- [x] CHK007 - Logs e telemetria são limitados a eventos agregados sem sujeito, recurso, permission key, explicação ou segredo? [Auditoria, Interface detalhes `Telemetry`] {auto} — cada interação exclui explicitamente esses dados.
- [x] CHK008 - Retenção da auditoria permanece atribuída ao mecanismo existente, sem a UI alegar controle de retenção? [Responsabilidade, Data Model §Registro de auditoria; Spec §Decisões de infraestrutura] {auto} — evento é imutável e o job de retenção existente é a autoridade.

## Ameaças e limites aprovados

- [x] CHK009 - Mudança de contexto, resposta obsoleta, revogação concorrente e relação herdada têm requisitos de falha segura? [Threat coverage, Spec §Casos de borda; Contract §Cache e concorrência; Interface estados] {auto} — reconsulta e confirmação nova impedem escrita em contexto incorreto.
- [x] CHK010 - MFA, compliance regulatório adicional e criptografia de infraestrutura não foram introduzidos implicitamente como requisito desta feature? [Escopo autorizado, Constituição I e IV; Spec §Fora de escopo] {auto} — a SDD preserva controles existentes e não amplia política de identidade ou infraestrutura.

## Notes

- Itens `{auto}` foram resolvidos com evidência citada.
- Não há itens `{humano}` ou gaps de requisitos abertos neste domínio.
