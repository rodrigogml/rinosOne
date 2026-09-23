# Plano de Implementação: Acesso de Usuário

**Feature**: `user-auth` | **Data**: 2026-09-22 | **Spec**: [spec.md](spec.md)

## Resumo

Implementar o acesso inicial com cadastro por e-mail, ativação por link ou código, senha opcional, login sem senha, autenticação persistente opcional e controle de sessões. A abordagem mantém um monólito modular: domínio de acesso independente, API JSON versionada, persistência MySQL, envio assíncrono de e-mail e interface web responsiva consumindo contratos explícitos.

## Contexto Técnico

**Linguagem/Versão**: PHP 8.4 e TypeScript 5.x.  
**Dependências principais**: Laravel 12; Vue 3; Vite; Pinia; Tailwind CSS 4.  
**Armazenamento**: MySQL `utf8mb4_unicode_ci`; o schema principal é `rinosone`, com reserva do padrão `rinosone_{tenantId}` para tenants futuros. Sessões, credenciais persistentes, cache e fila inicialmente persistidos no schema principal.  
**Testes**: PHPUnit para unidade e feature; Vitest e Vue Test Utils para a interface; Playwright para cenários end-to-end.  
**Plataforma-alvo**: Linux, servidor web, PHP-FPM, MySQL, worker de fila persistente e scheduler da aplicação.  
**Tipo de projeto**: aplicação web monolítica modular com SPA.  
**Metas de desempenho**: sem meta quantitativa aprovada; o fluxo não deve bloquear a resposta ao usuário durante o envio de e-mail.  
**Restrições**: somente acesso de usuário; configurações e segredos por ambiente; sessões sem expiração por inatividade; emissões descartadas sem retenção por rotina periódica configurável e logs de segurança com padrão de 30 dias configurável; nenhuma integração, produto ou módulo adicional.  
**Escala/Escopo**: sem previsão quantitativa aprovada; limites de autenticação são configuráveis por ambiente.

## Arquitetura da Superfície de Interação

**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)  
**Aplicabilidade do design de interface**: REQUIRED — a feature possui jornadas web críticas, estados de erro, autenticação e comportamento responsivo.

| Surface ID | Cobertura da feature | Decisão tecnológica | Módulo/repositório | Notas |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | FULL | Vue 3, TypeScript e navegador moderno | `resources/js` e `resources/css` (criados no bootstrap) | SPA responsiva com componentes próprios e Tailwind CSS 4. |

## Constitution Check

*GATE: aprovado antes do Phase 0 e revalidado após o design.*

| Princípio | Status | Notas |
| --- | --- | --- |
| Simplicidade incremental e escopo autorizado | PASS | O plano cobre exclusivamente acesso e não cria produtos, módulos ou integrações futuras. |
| Fronteira API e domínio independente da interface | PASS | A web consome contratos JSON; domínio e regras de segurança permanecem no backend. |
| Identidade e acesso seguros por padrão | PASS | Confirmação de e-mail, emissões temporárias, hash de segredo, limites e sessões são previstos. |
| Dados mínimos, sessões controladas e configuração segura | PASS | Persiste somente os dados de identidade, emissão e sessão; parâmetros sensíveis ficam no ambiente. |
| Mudanças verificáveis e documentação alinhada | PASS | O plano inclui contratos, modelo de dados, quickstart e estratégia de testes. |

## Estrutura do Projeto

### Documentação da feature

```text
docs/specs/user-auth/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── contracts/
    └── auth-api.md
```

### Código-fonte

O repositório ainda não possui código de produção. A primeira tarefa de implementação criará o bootstrap aprovado, incluindo os caminhos abaixo; nenhum outro módulo será criado nesta fase.

```text
app/
├── Domain/Access/             # regras de elegibilidade e emissões temporárias
├── Http/Controllers/Api/Auth/ # bordas HTTP da API de acesso
├── Http/Requests/Auth/        # validação de entrada
├── Jobs/                      # entrega assíncrona de e-mail
├── Mail/                      # mensagens de validação e acesso
└── Services/Access/           # orquestração de casos de uso
database/migrations/           # schema de usuários, emissões e sessões
resources/js/                  # SPA, rotas, stores e componentes de acesso
resources/css/                 # estilos e tokens da interface
routes/                        # rotas web e API
tests/Unit/                    # regras de domínio
tests/Feature/                 # API, persistência e segurança
tests/e2e/                     # jornadas reais de navegador
```

**Decisão de estrutura**: os diretórios são proposições do bootstrap, não módulos existentes. O domínio não conhece HTTP, ORM, e-mail ou componentes de interface; controllers e serviços coordenam os casos de uso nas bordas.

**Topologia de dados**: [database-topology.md](../../architecture/database-topology.md) define a separação entre o schema principal e schemas futuros de tenant. Nesta feature, todas as migrations e dados pertencem exclusivamente a `rinosone`; a separação física de migrations por tenant será introduzida junto do provisionamento autorizado.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Colunas do banco | `camelCase` | constraints e migrations | migrations do backend |
| Objetos de domínio | `camelCase` | construtores e regras de domínio | `app/Domain/Access` |
| DTOs do backend | `camelCase` | requests e responses | controllers e requests de acesso |
| DTOs da interface | `camelCase` | schema no cliente | tipos e cliente da SPA |
| Payloads da API | `camelCase` | request e response nos dois lados | [auth-api.md](contracts/auth-api.md) |
| Parâmetros de URL | `kebab-case` | roteamento | rotas web e API |

**Mapper layer (DB ↔ DTO)**: os serviços de acesso mapeiam registros persistidos para objetos de domínio e DTOs de resposta; controllers não acessam registros diretamente.

**Validação de schema**: requests são validados na API; responses são validadas por testes de contrato no backend e pelo parser da interface antes de atualizar o estado.

## Acompanhamento de Complexidade

Nenhuma violação da Constituição é necessária.
