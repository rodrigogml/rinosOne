# Cenários de Validação — Fundação de Razão Financeira

## Conta, abertura e saldo derivado

1. Criar conta bancária BRL ativa.
2. Criar e confirmar um fato de abertura de `1000.00`.
3. Consultar saldo até a data de abertura.
4. **Esperado**: saldo retorna `1000.00`; tentativa de criar segunda abertura confirmada é recusada sem novo efeito.

## Fato, extrato e reversão

1. Criar e confirmar saída manual de `125.50` em conta com abertura.
2. Consultar extrato e saldo.
3. Reverter o fato com chave de idempotência e repetir a mesma requisição.
4. **Esperado**: o extrato mostra original e reversão vinculada; saldo final retorna ao valor anterior; repetição não cria segunda reversão.

## Concorrência e fechamento

1. Criar rascunho e enviar duas confirmações com a mesma versão em paralelo.
2. Fechar a data efetiva do fato confirmado.
3. Tentar confirmar outro rascunho com data igual ou anterior ao fechamento.
4. **Esperado**: uma confirmação vence, a concorrente recebe `FINANCIAL_VERSION_CONFLICT`, e o fato protegido recebe `FINANCIAL_PERIOD_CLOSED` sem saldo parcial.

## Isolamento, autorização e identificador bancário

1. Criar conta e contraparte no tenant A.
2. Consultar o ID pelo tenant B e tentar usar Pessoa do tenant B na conta de A.
3. Consultar a mesma conta sem a capacidade de identificadores bancários.
4. **Esperado**: nenhuma consulta atravessa tenant; a referência de contraparte é recusada; a projeção não revela agência ou número de conta.

## Roundtrip API

1. Subir backend e web localmente com o schema do tenant atualizado.
2. Criar conta por `POST /api/v1/tenants/{tenantId}/financial/accounts` e confirmar uma abertura por API real.
3. Capturar a resposta de saldo e comparar nomes, tipos e valores com [financial-ledger-api.md](contracts/financial-ledger-api.md).
4. Consumir a mesma resposta na interface financeira tipada.
5. **Esperado**: contrato, payload e tipo do cliente usam `camelCase`, valores monetários textuais e não apresentam divergência.

## Roundtrip humano

1. Na Área de Trabalho, abrir Financeiro no tenant autorizado e criar uma conta.
2. Registrar e confirmar um fato manual; consultar o extrato e solicitar reversão.
3. **Esperado**: foco, confirmação crítica, feedback de erro/sucesso e saldo observável permanecem coerentes com o contrato. O detalhamento visual será definido em `interface-spec.md`.
