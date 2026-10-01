# Pesquisa e Proposta de Implementação — Serviço Indicadores Econômicos

## Decisão recomendada

Criar o Serviço de Indicadores Econômicos no core global, com conectores de fonte separados para SGS e PTAX. Ele será a autoridade de dados públicos de referência para a plataforma; módulos de tenant apenas consultam ou registram o *snapshot* de um indicador usado, sem copiar suas tabelas.

O núcleo deve cobrir SELIC, IPCA, IGP-M, INCC e remuneração da poupança. PTAX de fechamento para USD e EUR deve preservar as cotações de compra e venda; a cotação mediana, quando necessária, é derivada e marcada como tal.

## Estrutura proposta

| Componente | Escopo | Responsabilidade |
| --- | --- | --- |
| Série econômica | Core global | Define código estável, tipo, unidade, periodicidade, calendário, fonte e regra de acumulação de SELIC, IPCA, IGP-M, INCC ou poupança. |
| Observação econômica | Core global | Armazena o valor decimal publicado, data de referência, publicação/captura, identidade da fonte e revisão. |
| Cotação PTAX | Core global | Armazena moeda cotada, moeda de referência, data e horário de fechamento, compra, venda e mediana derivada. |
| Execução de atualização | Core global e operacional | Registra fonte, intervalo, estado, contagens, correlação e falha segura de cada carga. |
| Adaptador de fonte | Infraestrutura | Traduz o formato de SGS ou PTAX para um resultado validado, sem expor protocolo externo ao domínio. |
| Serviço de consulta | Domínio/API interna | Resolve observação ou cotação vigente para a data solicitada e retorna ausência explícita quando não houver dado. |

As entidades globais usam identificadores numéricos da plataforma. As tabelas operacionais do tenant podem referenciá-las somente no sentido tenant → global; um fato financeiro deve registrar também os valores e a identidade da observação efetivamente usada, pois uma revisão posterior da referência não altera o fato histórico.

## Modelo de dados candidato

### Série e observação de índice

`economicReferenceSeries` mantém a definição estável da série e evita enumerações rígidas no código. Seus atributos incluem código, nome, tipo de medida, unidade, periodicidade, calendário, fonte autorizada, identificador na fonte, regra de acumulação, data inicial permitida e estado.

`economicReferenceObservation` armazena uma observação de valor com data de referência, momento de publicação quando disponível, momento de captura, valor decimal, identidade de origem, revisão e estado de vigência. A mesma observação publicada novamente deve ser reconhecida como repetição; uma correção oficial cria uma revisão, sem sobrescrever a anterior.

O acumulado não deve ser a única fonte de verdade. Quando materializado para consultas de correção monetária, ele é uma projeção determinística das observações vigentes que o compõem e deve ser integralmente recalculado após toda atualização bem-sucedida. A recomposição usa os valores brutos em memória, com 36 casas decimais internas e `HALF_UP` somente na persistência de 18 casas; nunca encadeia o acumulado persistido e arredondado de uma execução anterior.

### Cotação PTAX

`economicPtaxQuote` representa uma cotação de fechamento para uma moeda e data de referência. Preserva compra e venda publicadas, horário de cotação, identidade de origem, revisão e estado de vigência. A mediana é calculada deterministicamente a partir de compra e venda, com precisão e convenção de arredondamento explícitas.

PTAX não deve ser reduzida a um único valor genérico: compra, venda e mediana têm semânticas diferentes para futuras liquidações e relatórios.

## Carga e atualização

1. A Central de Manutenções agenda a atualização com uma janela móvel sobreposta ao último período carregado; o mesmo serviço suporta recuperação manual autorizada e carga histórica delimitada.
2. O adaptador SGS consulta cada série aprovada, valida data, valor decimal e periodicidade, e devolve somente observações publicadas.
3. O adaptador PTAX consulta a moeda e período aprovados, seleciona exclusivamente o boletim de fechamento e valida compra, venda, moeda e horário.
4. O serviço compara a identidade de origem e o conteúdo. Repetições são idempotentes; divergência oficial gera revisão preservada.
5. A execução atualiza a vigência, recompõe desde a origem todos os acumulados das séries compatíveis e grava resultado operacional seguro. Falha de fonte não invalida dados anteriores.

O job deve ter bloqueio único entre réplicas, repetição segura, limite de tempo por fonte e correlação. Dados de resposta brutos não entram em logs operacionais, exceto metadados mínimos necessários para diagnóstico seguro.

## Regras matemáticas candidatas

Para séries `COMPOUND_PUBLISHED_RATE`, o fator acumulado parte de 100 na primeira observação e aplica sucessivamente `fatorAnterior × (1 + taxa/100)` apenas às observações publicadas posteriores, na ordem cronológica. A regra calcula em escala interna 36 e arredonda cada valor materializado para 18 casas com `HALF_UP`; o cálculo não preenche dias sem publicação. O fator de uma correção entre duas datas é recomposto dos valores brutos no intervalo, e não da divisão de acumulados persistidos.

A série SGS 195 de Poupança é diária, mas expressa rentabilidade de um período mensal que se encerra no aniversário da conta. O BCB informa que esse período depende da data de aniversário e que o crédito ocorre ao seu fim; valores de datas consecutivas, portanto, representam períodos sobrepostos. Ela adota `NO_GLOBAL_ACCUMULATION`: o sistema mantém o valor oficial, mas não cria um índice composto nem simula rentabilidade individual. [BCB — Remuneração dos Depósitos de Poupança](https://www.bcb.gov.br/estatisticas/remuneradepositospoupanca/)

Para PTAX, `mediana = (compra + venda) / 2` é uma derivação, não uma cotação oficial. Conversão financeira, escolha entre compra/venda/mediana, datas de cotação, arredondamento monetário e reconhecimento de diferença cambial são excluídos desta capacidade e serão decididos em `finance-currency-and-valuation`.

## Melhorias adotadas sobre uma carga simples

- Séries configuráveis no catálogo global, sem depender de enumeração de banco para acrescentar uma série aprovada.
- Revisões oficiais imutáveis, em vez de substituir um valor anterior por *upsert* sem histórico.
- Execução, agenda, bloqueio e observabilidade integrados à plataforma, em vez de script autônomo.
- Janela móvel sobreposta para detectar retificações e preencher lacunas; cargas históricas sempre explícitas e delimitadas.
- Separação tipada de PTAX para não perder compra, venda, horário e natureza derivada da mediana.
