# Cenários de Validação

1. Aquecer cache de allow e remover grant → **esperado:** a próxima operação nega usando versão atual.
2. Perder cache durante decisão → **esperado:** resultado é igual ao resolvedor persistente.
3. Enviar lote de actions/references → **esperado:** cada resultado coincide com o controle individual.
4. Listar recursos protegidos em volume → **esperado:** subconjunto autorizado e sem loop de `check` por item.
