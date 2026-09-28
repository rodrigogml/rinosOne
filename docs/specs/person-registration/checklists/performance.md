# Performance Checklist: Cadastro de Pessoas por Organização

**Purpose**: verificar se metas de busca e crescimento possuem condições, limites e rastreabilidade suficientes para planejamento e validação.  
**Created**: 2026-09-28  
**Feature**: [plan.md](../plan.md)

## Metas e consultas críticas

- [x] CHK-PERF-001 - A busca inicial possui meta mensurável de 95% das respostas em até 2 segundos? [Mensurabilidade, Spec CS-005; Plan §Contexto técnico] {auto}
- [x] CHK-PERF-002 - O modelo mapeia índices para documento, nome de exibição, nome, alias, contato e chaves Pix, relacionando-os às consultas críticas? [Rastreabilidade, Data model §person; Data model §personContact; Data model §personPixKey] {auto}
- [x] CHK-PERF-003 - O catálogo evita carregar as coleções completas na listagem e reserva o detalhe para consulta sob demanda? [Eficiência, Plan §Fluxos técnicos principais; Interface INT-WEB-PEOPLE-001] {auto}

## Lacunas de escala e degradação

- [x] CHK-PERF-004 - A meta de 2 segundos define página padrão 50, até 100.000 Pessoas e 25 consultas concorrentes autorizadas? [Condições de medição, Plan §Políticas gerais de API e concorrência] {auto}
- [x] CHK-PERF-005 - Limites de paginação, ordenação estável, corpo de requisição e ausência de teto específico de coleção estão definidos para evitar respostas sem limite? [Limites, Contract §Políticas gerais de operação; Contract §Criar ou atualizar Pessoa] {auto}
- [x] CHK-PERF-006 - A interface preserva lista anterior, sinaliza dado potencialmente desatualizado e oferece atualização durante degradação de busca? [Degradação, Interface INT-WEB-PEOPLE-001] {auto}
- [x] CHK-PERF-007 - A concorrência de alterações e exclusões define conflito por versão, idempotência e recuperação por recarga sem repetição indevida? [Confiabilidade, Plan §Políticas gerais de API e concorrência; Contract §Políticas gerais de operação] {auto}

## Observabilidade de desempenho

- [x] CHK-PERF-008 - Métricas e limiar de alerta para p95, erro, conflito e limitação estão definidos sem dados pessoais? [Observabilidade, Plan §Políticas gerais de API e concorrência] {auto}
- [x] CHK-PERF-009 - A decisão explícita de não usar cache compartilhado para lista ou detalhe de Pessoas está registrada? [Consistência, Research Decisão 9; Plan §Políticas gerais de API e concorrência] {auto}

## Notes

- Não há lacunas abertas neste domínio.
