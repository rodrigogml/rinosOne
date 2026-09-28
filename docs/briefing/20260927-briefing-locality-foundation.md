# Briefing da Feature: Fundação de Localidades

**Data**: 2026-09-27
**Status**: Confirmado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: a fundação global de referências territoriais e postais da plataforma: Países, UFs, Municípios, CEPs e referências de logradouros.

**Problema que resolve**: evitar que cada funcionalidade mantenha listas próprias de localidades ou trate CEP e nome de rua como identidades únicas, perdendo consistência quando fontes retornam resultados múltiplos, incompletos ou divergentes.

**Proposta de valor**: disponibilizar uma base local, reutilizável e progressivamente enriquecida para futuras funcionalidades de endereço, sem bloquear a pessoa usuária enquanto informações externas são consultadas.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
| --- | --- | --- |
| Pessoa usuária de funcionalidade futura | Consulta uma referência postal durante o preenchimento de um endereço. | Vê resultados locais imediatamente e recebe novos candidatos consolidados sem interromper a jornada. |
| Administrador da Plataforma | Acompanha a rotina territorial no Hub de Manutenções. | Consulta estado, última execução e histórico permitido da atualização IBGE. |
| Fonte externa habilitada | Origem de referência territorial ou postal. | Retorna dados territoriais, CEPs e logradouros para consolidação local. |

**Stakeholders de decisão**: responsável pelo produto da plataforma.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
| --- | --- | --- | --- | --- | --- |
| Hub de Manutenções | Administradores da Plataforma autorizados | Web responsiva | MVP | Online | Consulta de estado e histórico da rotina IBGE, sem disparo manual. |
| Funcionalidade futura consumidora de CEP | Pessoas usuárias autenticadas | Web responsiva | Pós-MVP | Online/intermitente | Mostrará resultados locais e progresso de enriquecimento conforme o contrato desta fundação. |
| Catálogo administrativo de localidades | Administradores da Plataforma autorizados | Web responsiva | Pós-MVP | Online | Tela de listagem, filtro, remoção e reativação será definida em feature própria. |

**Restrições tecnológicas já obrigatórias**: a plataforma usa Laravel, Vue 3, TypeScript, MySQL, filas persistentes e API JSON.

## 4. Escopo

### MVP (Essencial)

1. Catálogos globais de Países, UFs e Municípios, identificados internamente por `BIGINT`.
2. Atualização territorial pelo IBGE, executada automaticamente quando nunca realizada e verificada mensalmente depois disso.
3. Integração explícita da rotina territorial do IBGE no Hub de Manutenções, com execução singleton e sem ação manual.
4. Catálogo local de CEPs e referências de logradouro, sem tratá-los como endereço final.
5. Consulta local imediata e enriquecimento assíncrono por ViaCEP e BrasilAPI, consultadas em paralelo.
6. Disponibilização imediata de resultados retornados por fontes habilitadas, com remoção automática somente de duplicidades comprovadas.
7. Estados `ACTIVE` e `REMOVED`; resultados removidos não voltam a ser exibidos automaticamente, mas poderão ser reativados por uma futura administração de catálogo.

### Pós-MVP (Desejável)

1. Tela administrativa de localidades com pesquisa, filtros, remoção e reativação.
2. Integração de outros provedores, incluindo Correios caso credenciais e condições comerciais sejam aprovadas.
3. Uso da fundação por funcionalidades de endereços concretos de Pessoas e outros domínios.
4. Estrutura internacional de subdivisões e cidades, alimentada quando houver fonte apropriada.

### Fora de Escopo

- Cadastro de endereços concretos, números, complementos ou vínculos de endereço com Pessoas, organizações ou tenants.
- Catálogo de nacionalidades.
- Carga manual ou manutenção manual de dados vindos de fontes externas.
- Disparo manual da rotina IBGE.
- Tela autônoma de consulta de CEP nesta feature.
- Correios como provedor ativo inicial.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: integridade da referência territorial > resposta imediata com dados locais > enriquecimento contínuo > redução automática de duplicidades > abrangência de fontes.

**Decisões explícitas**:

- CEP representa um código postal; não identifica sozinho um logradouro, bairro ou endereço.
- Uma relação entre CEP e referência de logradouro pode ser muitos-para-muitos.
- A ausência de uma fonte em uma consulta isolada não torna uma referência local automaticamente inválida.
- Qualquer resultado de fonte habilitada fica disponível imediatamente; divergências relevantes coexistem.
- Somente equivalências fortes e determinísticas podem gerar remoção automática por duplicidade; regras específicas serão avaliadas por fonte durante o planejamento técnico.
- Remoção é lógica e persistente para a automação: uma fonte não pode reativar sozinha uma referência `REMOVED`.
- A pessoa usuária não vê nomes de fontes, diagnósticos técnicos ou detalhes de falha.

## 6. Restrições

| Restrição | Valor | Notas |
| --- | --- | --- |
| Identidade | `BIGINT` | Não utilizar ULID nas entidades da fundação. |
| Ownership | Core global | Tenants referenciam o core; o core não referencia tenants. |
| Integridade | Sem `RESTRICT` | Relações futuras seguem a política já definida de atualização/remoção não restritiva. |
| Disponibilidade | Base local sempre utilizável | Falha externa não pode bloquear uma funcionalidade consumidora. |
| Provedores iniciais | ViaCEP e BrasilAPI | Consultados em paralelo quando habilitados. |
| Atualização IBGE | Inicial automática + mensal | Sem botão ou cadastro manual. |
| Concorrência IBGE | Singleton | Execuções simultâneas são recusadas. |
| Atualização visual futura | Consulta periódica de 1 segundo | Somente enquanto a tela consumidora estiver aberta e houver enriquecimento pendente. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Backend | PHP e Laravel | Stack obrigatória da plataforma e fronteira para integrações externas. |
| Interface administrativa | Web responsiva existente | O Hub já apresenta rotinas de manutenção da Plataforma. |
| Banco de dados | MySQL | Catálogos globais e referências compartilhadas da plataforma. |
| Processamento assíncrono | Fila persistente existente | Permite concluir o enriquecimento depois que a pessoa usuária avançar no fluxo. |
| Integrações | IBGE, ViaCEP e BrasilAPI | Fonte territorial brasileira e provedores iniciais de referência postal. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Preservar identidade técnica independente de textos, CEPs e respostas de provedores.
- Normalizar respostas externas em contrato interno sem expor sua origem à pessoa usuária.
- Registrar origem, identificador externo quando houver e última observação como atributos operacionais das referências; não criar tabela de auditoria de cargas.
- Manter histórico técnico e auditoria administrativa da rotina IBGE conforme a política configurável do Hub de Manutenções.
- Aplicar tempos limite, falha isolada por provedor, retentativa controlada e fallback para que uma fonte não indisponibilize a consulta.
- Testar resultados múltiplos, divergências territoriais, supressão persistente, atualização assíncrona e concorrência da rotina IBGE.

**Compliance**: referências territoriais e postais não são, isoladamente, dados pessoais; futuras funcionalidades de endereço deverão aplicar as políticas de acesso e privacidade próprias.

## 9. Visão de Futuro

**6 meses**: funcionalidades de Pessoas e outros domínios usam a mesma consulta de CEP e referências territoriais.

**12 meses**: a Plataforma dispõe de catálogo administrativo de localidades e, conforme fontes aprovadas, amplia a cobertura postal internacional e de provedores.

**Riscos conhecidos**:

- Fontes diferentes podem retornar textos distintos para a mesma referência ou resultados materialmente conflitantes.
- Consultas externas podem ser lentas, limitadas ou indisponíveis.
- Regras excessivamente agressivas de duplicidade podem ocultar uma referência legítima.

---

## Itens a Definir

| Item | Dimensão | Impacto |
| --- | --- | --- |
| Regras determinísticas por fonte para remoção automática de duplicidades | Planejamento técnico | Alto |
| Tempos limite, retentativas, backoff e circuit breaker dos provedores | Resiliência | Alto |
| Contrato detalhado de consulta e acompanhamento do enriquecimento | API e experiência futura | Médio |
| Modelo físico de CEP, logradouro, observações e identificadores externos | Dados | Alto |

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para formalizar os requisitos funcionais da feature.
