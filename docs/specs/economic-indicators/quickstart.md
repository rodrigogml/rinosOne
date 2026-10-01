# Cenários de Validação — Indicadores Econômicos

## Conectividade segura com BCB

Os adaptadores do BCB nunca desativam a validação TLS. Em Windows, `BCB_USE_NATIVE_CA=true` (padrão) permite ao cURL/PHP usar o repositório confiável do sistema quando não houver `BCB_CA_BUNDLE`; isso cobre cadeias válidas que o OpenSSL local ainda não possua. Em ambiente que usa um bundle corporativo ou de infraestrutura, configure `BCB_CA_BUNDLE` com seu caminho confiável; ele tem precedência sobre a CA nativa.

## Promoção controlada

Antes de promover para homologação ou produção:

1. Disponibilizar o código e executar `php artisan migrate:global --force` no ambiente-alvo.
2. Confirmar que o PHP confia na cadeia TLS do BCB: em Windows, manter `BCB_USE_NATIVE_CA=true`; em infraestrutura com cadeia própria, configurar `BCB_CA_BUNDLE` sem desativar `verify`.
3. Executar `php artisan maintenance:economic-indicators:sync`, conferir resultado `SUCCEEDED` no Hub e verificar os detalhes por série, fonte e intervalo.
4. Após a primeira janela diária agendada, confirmar uma segunda execução idempotente sem criações ou revisões inesperadas.

## Carga idempotente

1. Executar a primeira rotina com respostas oficiais simuladas desde a data inicial da série, em múltiplas janelas quando necessário.
2. Executar novamente com o mesmo conteúdo.
3. **Esperado**: uma observação vigente por série/data e uma cotação vigente por moeda/data, sem duplicação.

> [!IMPORTANT]
> A comparação de valores é numérica na precisão persistida, e não textual. Assim, valores equivalentes como `0,050788` e `0,050788000000` não criam uma revisão.
> Se uma execução anterior tiver produzido uma revisão de escala sem versão histórica, a próxima rotina normaliza automaticamente o número da revisão.

## Carga histórica inicial

1. Configurar uma janela histórica menor que o período entre a data inicial da série e a data de execução.
2. Executar a primeira sincronização.
3. **Esperado**: o primeiro pedido inicia na data inicial configurada, as janelas percorrem o período completo e `firstReferenceDate` registra a primeira data publicada retornada. Depois da carga, uma nova execução usa apenas a janela incremental de sobreposição.

Quando o SGS responder com o envelope oficial ou HTTP 404 de “nenhum valor no período”, a atualização incremental é bem-sucedida sem criar valor artificial ou registrar uma falha operacional.

## Execução operacional limitada

Para diagnóstico controlado ou recuperação de uma fonte, a execução operacional pode limitar o processo atual a séries explícitas, sem alterar dados manualmente:

```powershell
php artisan maintenance:economic-indicators:sync --series=SELIC_DAILY
```

O comando usa o mesmo lock, histórico e tratamento de falha da rotina agendada. Sem `--series`, a rotina processa todo o catálogo. A configuração `ECONOMIC_INDICATOR_ENABLED_SERIES` permite a mesma restrição para um processo agendado específico; ela não deve ser definida na operação normal.

O detalhe técnico seguro de cada execução inclui, por série, fonte, intervalo, criações, revisões e quantidade de acumulados recompostos. Em uma falha externa, identifica somente a série, o intervalo e o código de falha.

## Revisão oficial

1. Carregar uma observação publicada.
2. Reexecutar com mesma identidade econômica e valor oficial revisado.
3. **Esperado**: a versão anterior permanece histórica e a revisão passa a vigente.

## Acumulado e projeção por índice

1. Sincronizar uma série `COMPOUND_PUBLISHED_RATE` com ao menos três observações.
2. Confirmar base `100` na primeira observação e composição cronológica dos valores brutos posteriores.
3. Alterar uma revisão ou um `accumulatedValue` materializado em ambiente descartável e repetir a sincronização.
4. **Esperado**: toda a sequência vigente é recomposta dos valores brutos, as versões históricas ficam sem acumulado e a projeção entre duas datas exatas retorna fator, taxa e valor atualizado com 18 casas decimais.

> [!NOTE]
> Poupança é `NO_GLOBAL_ACCUMULATION`: sua publicação diária corresponde a período mensal de aniversário de conta e não pode ser composta entre datas consecutivas.

## Indisponibilidade da fonte

1. Carregar uma observação válida.
2. Simular indisponibilidade de SGS ou PTAX na próxima execução.
3. **Esperado**: a consulta continua retornando o último valor válido e o Hub registra falha segura.

## Consulta interna

1. Solicitar uma série e data que possuem valor vigente.
2. Solicitar data sem publicação.
3. **Esperado**: a primeira consulta inclui origem e revisão; a segunda retorna ausência explícita, nunca zero ou valor estimado.
