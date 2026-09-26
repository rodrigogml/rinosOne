# Cenários de Validação Catálogo de Instituições Financeiras

## Cenário 1: Carga inicial oficial

1. Disponibilizar uma resposta válida do BCB com instituição autorizada em atividade e seu `codigoIdentificadorBacen`.
2. Invocar explicitamente a operação de atualização.
3. Consultar o catálogo global.
4. **Esperado**: existe um único registro com a identidade interna criada, disponibilidade para seleção ativa e atributos oficiais mapeados.

## Cenário 2: Reexecução idempotente

1. Executar uma carga válida.
2. Executar novamente a mesma carga oficial.
3. Consultar quantidade e identidade das instituições.
4. **Esperado**: a quantidade não cresce e o registro mantém o mesmo `id` interno.

## Cenário 3: Alteração e inativação oficial

1. Carregar uma instituição autorizada em atividade.
2. Executar nova atualização em que a mesma identidade BCB contém nome alterado ou situação oficial diferente.
3. Consultar o catálogo.
4. **Esperado**: o mesmo `id` interno contém os atributos novos; situação diferente de autorizada em atividade torna a instituição indisponível somente para novos vínculos.

## Cenário 4: Ausência ou falha da fonte

1. Carregar uma instituição válida.
2. Invocar uma atualização cuja resposta não contenha o registro, ou simular indisponibilidade do BCB.
3. Consultar o catálogo e os logs operacionais.
4. **Esperado**: o registro anterior não é removido nem inativado pela ausência; em falha, o catálogo anterior permanece disponível e o erro é registrado sem segredo ou payload sensível.

## Cenário 5: Ausência de agendamento autônomo

1. Inicializar a aplicação sem solicitar atualização.
2. Verificar os pontos de agendamento e a quantidade de chamadas à fonte BCB.
3. **Esperado**: nenhuma atualização é iniciada automaticamente; a operação permanece disponível para teste, acionamento administrativo futuro e integração pela central de manutenções.
