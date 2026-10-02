# Pesquisa — Fundação de Razão Financeira

## Decisão 1: Razão imutável, saldo derivado

**Decisão**: cada efeito econômico confirmado será um `financialFact` imutável. Abertura, lançamento manual, reversão e ajuste são espécies tipadas do mesmo registro; somente fatos `CONFIRMED` compõem o saldo. Correções criam novos fatos relacionados, nunca atualizam um fato confirmado.

**Racional**: a regra preserva a história econômica, permite explicar o saldo e impede que um update silencioso altere um período já conferido. O saldo será calculado sob demanda por abertura, entradas e saídas, sem coluna materializada de saldo atual.

**Alternativas consideradas**: atualizar uma coluna de saldo em `financialAccount` foi rejeitado porque criaria risco de divergência e exigiria rotinas adicionais de reparo. Usar apenas um log sem entidade de conta mutável foi rejeitado porque a conta ainda tem ciclo de vida, identificadores e versão própria.

## Decisão 2: Propriedade tenant e referências externas mínimas

**Decisão**: todas as tabelas da fundação pertencem ao schema do tenant. A conta pode referenciar opcionalmente `financialInstitution` e o auditor pode referenciar opcionalmente `user`, ambos no core global, com `ON UPDATE CASCADE` e `ON DELETE SET NULL`. A contraparte é uma `Person` do mesmo schema de tenant.

**Racional**: reproduz a topologia já usada por Pessoas, respeita a direção tenant → core e evita duplicar catálogos ou dados de contrapartes.

**Alternativas consideradas**: copiar instituição financeira ou pessoa para o módulo foi rejeitado por criar duas fontes de verdade. Manter FKs do core para dados financeiros do tenant violaria a Constituição.

## Decisão 3: Moeda da conta e precisão nesta fundação

**Decisão**: uma conta tem exatamente uma moeda ISO 4217 entre `BRL`, `USD` e `EUR`. Valores monetários usam `DECIMAL(19,2)` positivo e direção explícita; não há cotação, conversão, reavaliação nem transferência entre moedas neste SDD.

**Racional**: as três moedas cobrem o recorte aprovado e mantêm valores monetários determinísticos. PTAX e indicadores já disponíveis são referências globais, mas não constituem autorização para criar regra cambial.

**Alternativas consideradas**: restringir a fundação a BRL impediria contas em USD/EUR já aprovadas. Aceitar qualquer moeda ISO anteciparia uma política de escala e arredondamento ainda não especificada. Usar taxa de câmbio no lançamento misturaria esta fundação com `finance-currency-and-valuation`.

## Decisão 4: Fechamento como controle corrente e auditoria imutável

**Decisão**: `financialControl` é o único registro corrente por tenant e guarda a data efetivamente fechada e sua versão. Cada fechamento e reabertura gera um `financialAuditEvent` imutável, com ator, correlação, origem e justificativa de reabertura.

**Racional**: a checagem transacional precisa de uma autoridade simples para bloquear fatos por data, enquanto a auditoria precisa manter todos os eventos sem snapshots de dados bancários ou monetários desnecessários.

**Alternativas consideradas**: inferir o fechamento apenas pelo último evento dificultaria o lock e a validação concorrente. Guardar payload completo de cada fato na auditoria duplicaria informação sensível e a própria história econômica.

## Decisão 5: Interface e contrato

**Decisão**: a API JSON versionada será a fronteira de domínio e a web responsiva a consumirá como uma superfície da Área de Trabalho. O desenho detalhado de telas fica para `interface-spec.md`, após este plano; a API não será moldada por uma tela específica.

**Racional**: atende à Constituição e preserva consumidores futuros sem criar uma segunda implementação de regras no Vue.

**Alternativas consideradas**: uma tela acoplada diretamente às tabelas foi rejeitada por quebrar autorização, concorrência e possibilidade de integração futura.
