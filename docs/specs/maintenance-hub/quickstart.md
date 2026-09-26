# Cenários de Validação — Central de Manutenções

## Cenário 1: Consulta centralizada

1. Integrar a rotina de instituições financeiras na central.
2. Acessar a área administrativa como usuário autorizado para essa rotina.
3. Consultar a rotina e seu último histórico técnico.
4. **Esperado**: a central apresenta estado, dados seguros e ausência de execução anterior quando aplicável.

## Cenário 2: Disparo manual permitido

1. Acessar a rotina de instituições financeiras com permissão de disparo manual.
2. Solicitar uma atualização.
3. Consultar a auditoria e o histórico técnico resultante.
4. **Esperado**: a solicitação é encaminhada uma única vez ao motor da rotina, fica auditada e seu resultado é acompanhado separadamente.

## Cenário 3: Ação não permitida

1. Consultar uma integração que não autoriza disparo manual ou com usuário sem a ação requerida.
2. Tentar localizar ou solicitar o disparo.
3. **Esperado**: o controle não é disponibilizado ou a solicitação é recusada e auditada sem iniciar a rotina.

## Cenário 4: Agenda diária de instituições financeiras

1. Aguardar a ocorrência diária definida para a integração de instituições financeiras.
2. Consultar a central e o histórico técnico após a ocorrência.
3. **Esperado**: há uma única execução programada segundo a regra da rotina; nenhuma agenda paralela é registrada fora da central.

## Cenário 5: Retenção independente

1. Criar histórico técnico e auditoria administrativa com datas de expiração distintas.
2. Executar a limpeza de retenção.
3. **Esperado**: cada conjunto é removido apenas após sua própria expiração configurada, sem misturar registros.
