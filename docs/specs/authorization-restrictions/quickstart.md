# Cenários de Validação

1. Conceder role TENANT e criar restriction direta para a mesma permission → **esperado:** a próxima operação é negada.
2. Criar restriction de grupo e incluir pessoa → **esperado:** a pessoa é negada; removê-la do grupo remove o efeito na próxima operação.
3. Criar restriction TENANT no Tenant A e tentar mesma ação no Tenant B → **esperado:** não há efeito cruzado.
4. Definir `endsAt` no instante atual → **esperado:** a restriction já não é aplicável.
