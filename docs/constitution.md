<!--
Sync Impact Report
- Version: none -> 1.0.0
- Princípios modificados: criação inicial dos princípios de arquitetura incremental, fronteira API, identidade segura, configuração por ambiente e qualidade verificável.
- Seções adicionadas: Limites de Arquitetura e Dados; Processo e Documentação.
- Seções removidas: nenhuma.
- Artefatos que precisam atualização: docs/briefing/20260922-briefing-inicial.md (alinhado); specs, plans, interfaces e tasks futuros devem demonstrar conformidade.
- TODOs pendentes: compliance, retenção, equipe, prazo e budget.
-->

# Constituição do Rinos One

## Core Principles

### I. Simplicidade incremental e escopo autorizado

O sistema DEVE implementar apenas a capacidade aprovada para a fase atual. Produtos, módulos, papéis, integrações, entidades e abstrações que não sejam necessários à capacidade aprovada NÃO DEVEM ser criados antecipadamente. Uma ampliação de escopo exige briefing ou especificação aprovada.

**Racional**: a plataforma deve evoluir de forma direta, sem burocracia ou estruturas extensas antes de serem necessárias.

### II. Fronteira API e domínio independente da interface

Toda capacidade de negócio DEVE ser exposta por uma API JSON versionada. Regras de domínio NÃO DEVEM depender de componentes Vue, HTTP ou detalhes de renderização; a interface web e futuras interfaces ou integrações devem consumir contratos explícitos da API.

**Racional**: a plataforma começa com uma interface web, mas deve preservar uma fronteira estável para expansão sem acoplar a regra de negócio à interface atual.

### III. Identidade e acesso seguros por padrão

Somente usuários com e-mail validado DEVEM obter acesso autenticado. Senhas DEVEM ser persistidas exclusivamente com hash forte; links e códigos de acesso DEVEM ser aleatórios, de uso único, expirados e invalidados quando uma nova emissão substituir a anterior. Endpoints de acesso DEVEM limitar emissões e tentativas e não DEVEM revelar publicamente a existência de um cadastro.

**Racional**: a primeira capacidade da plataforma é identidade e acesso, portanto sua segurança não pode ser postergada.

### IV. Dados mínimos, sessões controladas e configuração segura

O sistema DEVE coletar e persistir somente os dados necessários à capacidade aprovada. Sessões DEVEM permanecer no servidor e permitir o encerramento da sessão atual e a invalidação das demais sessões. Credenciais, segredos e parâmetros reais de infraestrutura NÃO DEVEM ser versionados; o repositório DEVE conter somente modelos de configuração comentados e seguros.

**Racional**: acesso e configuração tratam dados sensíveis e precisam permanecer controlados desde a primeira implantação.

### V. Mudanças verificáveis e documentação alinhada

Toda mudança de comportamento DEVE ter testes automatizados proporcionais ao risco. Antes da entrega, a automação DEVE executar formatação, testes de backend e frontend, verificação de tipos e build de produção. Briefings, especificações, planos, contratos, interfaces e tarefas DEVEM evoluir junto da mudança que alterarem.

**Racional**: qualidade e rastreabilidade reduzem regressões sem substituir a simplicidade do escopo.

## Limites de Arquitetura e Dados

O produto é um monólito modular em PHP e Laravel, com interface web responsiva em Vue 3 e TypeScript, MySQL, API JSON versionada e sessões server-side. A organização deve separar domínio, API, interface e infraestrutura. A configuração concreta de e-mail é externa ao repositório e definida por ambiente. Nenhum produto, módulo ou integração adicional é autorizado por este documento.

## Processo e Documentação

O ciclo de desenvolvimento começa com briefing e Constituição. Cada capacidade aprovada deve possuir uma especificação antes de planejamento técnico e implementação. Quando aplicável, a especificação deve ser complementada por plano técnico, contrato de API, especificação de interface, checklists e tarefas executáveis. Exceções duradouras que alterem arquitetura, segurança, dados, contratos ou os princípios desta Constituição DEVEM ser registradas em ADR e, quando contrariarem um princípio, exigem emenda explícita.

## Governance

Esta Constituição prevalece sobre convenções implícitas e orienta decisões de arquitetura, qualidade e processo. Uma emenda exige justificativa, análise de impacto nos artefatos afetados e atualização deste documento. O versionamento segue SemVer: MAJOR para remoção ou redefinição incompatível de princípio; MINOR para adição ou expansão material; PATCH para esclarecimentos sem mudança semântica. Nenhuma decisão futura pode inferir autorização para expandir o escopo atual.

**Version**: 1.0.0 | **Ratified**: 2026-09-22 | **Last Amended**: 2026-09-22
