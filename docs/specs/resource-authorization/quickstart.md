# Cenários de Validação

1. Compartilhar pasta pessoal A com leitura para B → **esperado:** B lê A, não edita e não vê pasta irmã.
2. Associar pessoa à conta A do Tenant A → **esperado:** a action só funciona em A; Conta B e Tenant B negam.
3. Revogar relation → **esperado:** próxima operação e lista deixam de incluir o recurso.
4. Roundtrip workspace: buscar lista real → parser TypeScript valida contrato → **esperado:** somente itens autorizados aparecem.
