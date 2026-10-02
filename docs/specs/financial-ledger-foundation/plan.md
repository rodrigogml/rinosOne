# Plano Técnico — Fundação de Razão Financeira

**Feature**: `financial-ledger-foundation` | **Data**: 2026-10-01 | **Spec**: [spec.md](spec.md)

## Resumo

Implementar a base financeira por tenant com contas de moeda única, fatos monetários imutáveis, saldo derivado, reversão explícita, fechamento temporal e auditoria mínima. A implementação reutilizará conexões de tenant, autorização, idempotência, Pessoas, catálogo global de instituições e arquivos privados existentes. Conversão cambial, classificação, títulos, conciliação e CNAB ficam fora desta capacidade.

## Contexto Técnico

**Linguagem/versão**: PHP 8.5, Laravel 12, TypeScript 5 e Vue 3.
**Dependências principais**: Eloquent, MySQL, middleware de contexto/autorização de tenant, idempotência e Vue I18n.
**Persistência**: MySQL 9; tabelas novas em cada schema `rinosone_{tenantId}`; referências opcionais ao core global.
**Testes**: PHPUnit/Laravel, Vitest, Playwright, `vue-tsc` e Vite.
**Plataforma alvo**: web responsiva na Área de Trabalho e API JSON `/api/v1`.
**Tipo de projeto**: monólito modular multi-tenant.
**Meta de desempenho**: saldo e primeira página de extrato por conta em até 200 ms p95 no volume homologado, com índice por conta/data/estado.
**Restrições**: valores são positivos com direção explícita; BRL/USD/EUR, duas casas monetárias, sem coluna de saldo, sem conversão e sem acesso cross-tenant.
**Escopo**: contas, fatos manuais, reversões, fechamento e auditoria; classificação, câmbio, títulos e arquivos bancários pertencem a SDDs posteriores.

## Arquitetura

1. O middleware já existente autoriza o tenant e entrega uma `ConnectionInterface` resolvida ao controlador financeiro.
2. Serviços `FinancialLedger` encapsulam criação de contas, ciclo de vida, rascunho/confirmação/reversão de fatos, cálculo de saldo, fechamento e auditoria; modelos `TenantScopedModel` nunca escolhem conexão por conta própria.
3. A confirmação ou reversão ocorre em uma única transação: bloqueia conta, fato e `financialControl`; valida estado, versão, idempotência e fechamento; persiste fato e evento de auditoria; só então confirma a resposta. Um inspetor no serviço de exclusão de Pessoas impede a remoção ordinária de uma contraparte financeira referenciada.
4. Consulta de saldo agrega fatos `CONFIRMED` por direção e data efetiva. Extrato pagina em ordem determinística e não usa a auditoria como fonte econômica.
5. A API expõe DTOs e resources camelCase. O cliente Vue usa tipos e um adaptador HTTP próprio; regras de saldo, permissão, fechamento e arredondamento permanecem no backend.

## Arquitetura das Superfícies

**Catálogo**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Aplicabilidade do desenho de interface**: **REQUIRED** — a feature cria uma superfície humana responsiva com fluxos críticos e confirmação de reversão/fechamento.

| Surface ID | Cobertura | Decisão tecnológica | Módulo | Notas |
| --- | --- | --- | --- | --- |
| SURF-WEB-FINANCE | FULL | Vue 3, TypeScript, Vite e navegador moderno | `resources/js`, `resources/css` | Área de Trabalho, i18n e design system existente. |
| API JSON financeira | FULL | Laravel, requests, resources e serviços | `app`, `routes/api` | Contrato em [financial-ledger-api.md](contracts/financial-ledger-api.md). |
| Referências econômicas | DEFERRED | Serviço interno existente | `app/Services/EconomicIndicator` | Só `finance-currency-and-valuation` consome PTAX. |

## Constitution Check

*GATE: aprovado antes e depois do desenho técnico.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental | PASS | Não introduz câmbio, classificação, cobrança ou CNAB. |
| II. Fronteira API e domínio | PASS | API versionada e domínio independente da web. |
| III. Identidade e acesso | PASS | Reutiliza autenticação e capabilities de tenant. |
| IV. Dados mínimos | PASS | Auditoria não duplica valores ou identificadores bancários; detalhes bancários têm projeção restrita. |
| V. Mudanças verificáveis | PASS | Quickstart e testes por domínio, API, web e E2E estão previstos. |
| VI. Identidades e referências | PASS | BIGINT e FKs unidirecionais tenant → core com exclusão explícita. |

## Modelo de Dados e Migração

O modelo completo está em [data-model.md](data-model.md). A migration de tenant cria as cinco tabelas e índices associados. Ela também registra permissões financeiras em migration core separada; schemas novos as recebem pelo catálogo e schemas existentes pela rotina de atualização de tenant.

Não haverá FK para o serviço global de indicadores nesta capacidade. A FK opcional para `financialInstitution` usa `nullOnDelete`; as referências a `user` e Pessoa seguem o mesmo princípio de conservação de histórico.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Tabelas e colunas MySQL | camelCase | constraints, FKs e índices da migration | `database/migrations/tenant/` |
| Modelos e serviços PHP | camelCase | requests, value objects e testes de domínio | `app/Models`, `app/Services` |
| Payload API e query params | camelCase | Form Requests e Resources | [financial-ledger-api.md](contracts/financial-ledger-api.md) |
| Cliente Vue | camelCase | tipos e parser no adaptador HTTP | `resources/js/financial` |
| Rotas | kebab-case no segmento composto | router e testes de contrato | `routes/api/authenticated.php` |

**Mapper DB ↔ DTO**: Resources e serviços de projeção em `app/Http/Resources/FinancialLedger` são responsáveis por converter modelos tenant-scoped em payloads.
**Validação de schema**: Form Requests validam comandos; o adaptador `resources/js/financial/financialApi.ts` valida a forma mínima das respostas antes de atualizar estado da interface.

## Estrutura do Projeto

### Documentação

```text
docs/specs/financial-ledger-foundation/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── contracts/
    └── financial-ledger-api.md
```

### Código (adições planejadas a diretórios existentes)

```text
app/
├── Domain/FinancialLedger/              # enums e value objects financeiros
├── Http/Controllers/Api/V1/FinancialLedger/
├── Http/Requests/FinancialLedger/
├── Http/Resources/FinancialLedger/
├── Models/                              # modelos tenant-scoped
└── Services/FinancialLedger/
database/migrations/
├── core/                                # permissões financeiras
└── tenant/                              # razão financeira
resources/js/financial/                  # cliente e superfície Vue
tests/{Unit,Feature}/FinancialLedger/
```

**Decisão estrutural**: o pacote `FinancialLedger` delimita a fundação e evita os pacotes genéricos. Ele consulta Pessoas e instituições por portas existentes, sem copiar seus modelos ou tabelas.

## Cenários de Validação

Os cenários de saldo, reversão, fechamento, isolamento, roundtrip de API e roundtrip humano estão em [quickstart.md](quickstart.md). A implementação deve acrescentar testes de migration, propriedades de saldo, concorrência, idempotência, autorização, mascaramento de dados bancários, API e interface.

## Complexidade

Nenhuma violação da Constituição foi necessária.
