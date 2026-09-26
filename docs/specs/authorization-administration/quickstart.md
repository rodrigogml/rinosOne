# Cenários de Validação

1. Administrador do Tenant A cria role e concede a membro ativo → **esperado:** action real é permitida no A.
2. Tentar conceder no Tenant B ou a não membro → **esperado:** erro seguro e nenhuma escrita.
3. Consultar effective access e explain para pessoa autorizada → **esperado:** fatores próprios do contexto, sem dados de terceiros.
4. Roundtrip web: salvar role → API real → atualizar lista/capability → **esperado:** confirmação acessível e novo estado observado.
