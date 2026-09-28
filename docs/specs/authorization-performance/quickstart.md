# Cenários de Validação

1. Aquecer cache de allow e remover grant → **esperado:** a próxima operação nega usando versão atual.
2. Perder cache durante decisão → **esperado:** resultado é igual ao resolvedor persistente.
3. Enviar lote de actions/references → **esperado:** cada resultado coincide com o controle individual.
4. Listar recursos protegidos em volume → **esperado:** subconjunto autorizado e sem loop de `check` por item.

## Benchmark reproduzível

Execute `php artisan test --testsuite=Performance`. O teste usa SQLite em memória e cobre check direto, grupo aninhado, recurso compartilhado, lote de 20 decisões, listagem de 30 pastas e a decisão imediatamente posterior à invalidação por restriction.

O limite padrão é de 1.000 ms por cenário, configurável por `AUTHORIZATION_BENCHMARK_MAX_MILLISECONDS`. Ele é uma proteção contra regressão funcional de consulta, não uma meta de capacidade ou SLA: a medição de capacidade deve ocorrer no ambiente representativo, com a base e o cache reais.
