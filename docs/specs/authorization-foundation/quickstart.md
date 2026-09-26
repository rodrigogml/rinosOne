# Quickstart: Fundação de Autorização

## Cenário 1: Administrador inicial de tenant

1. Uma pessoa autenticada cria um tenant.
2. O sistema cria membership ativa e uma atribuição direta de `tenant.administrator` na mesma transação.
3. A pessoa solicita a alteração de disponibilidade do tenant.
4. **Esperado**: a decisão encontra `tenant.availability.manage` na role administrativa e a alteração é permitida.

## Cenário 2: Membership sem grant administrativo

1. Uma pessoa possui membership ativa no tenant, mas não possui atribuição direta ou por grupo que conceda `tenant.availability.manage`.
2. Ela solicita a alteração de disponibilidade.
3. **Esperado**: a operação é negada com código seguro de autorização, sem modificar o tenant.

## Cenário 3: Isolamento entre tenants

1. Uma pessoa recebe uma role com permission de tenant no Tenant A.
2. Ela tenta executar a mesma ação no Tenant B.
3. **Esperado**: a decisão do Tenant B é negada, mesmo que a pessoa tenha membership ativa nele.

## Cenário 4: Revogação imediata

1. Uma pessoa autenticada recebe uma role por atribuição direta ou grupo.
2. A atribuição, grupo ou membership é inativada.
3. A pessoa executa nova operação protegida sem fazer novo login.
4. **Esperado**: a nova decisão é negada.

## Cenário 5: Roundtrip API e web

1. Criar tenant e obter a lista de tenants pela API real.
2. Confirmar que o item devolve `canManageAvailability` como booleano e não expõe a role da membership.
3. A web processa o payload pelo parser de tenant e mostra a ação de disponibilidade somente quando a capability é verdadeira.
4. Submeter a alteração de disponibilidade pela web.
5. **Esperado**: o backend repete a decisão de permission e o estado observado coincide com o payload recebido.
