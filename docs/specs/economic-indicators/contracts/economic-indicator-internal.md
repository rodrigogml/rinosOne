# Contrato Interno — Indicadores Econômicos

Somente serviços autorizados usam esta porta. Não há endpoint público ou de tenant para consultar tabelas globais.

## Consultar indicador

Entrada: código de série e data de referência.

Saída: código, valor decimal, acumulado quando a série o definir, unidade, data de referência, fonte, revisão e identidade da observação vigente; retorna ausência explícita se não houver publicação aplicável.

## Projetar correção por índice

Entrada: código de série acumulável, `startDate`, `endDate` e valor decimal de entrada. As duas datas devem corresponder exatamente a observações vigentes e `startDate` não pode ser posterior a `endDate`.

Saída: código, datas, valor de entrada, fator de atualização, taxa percentual e valor atualizado, todos com 18 casas decimais. O fator é recomposto dos valores brutos vigentes entre as datas, sem ler o acumulado materializado. Série sem acumulação global, data inexistente ou período inválido retorna erro explícito; nunca há interpolação ou estimativa.

## Consultar PTAX

Entrada: moeda `USD` ou `EUR` e data de referência.

Saída: compra, venda, mediana derivada, instante de cotação, fonte, revisão e identidade da cotação vigente; retorna ausência explícita se não houver fechamento publicado.

## Sincronizar

O Hub solicita atualização sem expor parâmetros de fonte. Para cada série processada, a execução registra código da fonte, intervalo consultado, contagens de criações, revisões e observações com acumulado recomposto; em falha de fonte, registra a série, o intervalo e o código técnico seguro. Repetição e concorrência não podem duplicar observações ou cotações.
