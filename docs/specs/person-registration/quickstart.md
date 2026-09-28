# Guia de Validação — Cadastro de Pessoas por Organização

## Pré-requisitos

- Duas organizações ativas, cada uma com um schema provisionado.
- Usuário autenticado com contexto e permissões de Pessoa para a primeira organização.
- Catálogos corporativos de País, UF, Município, Localidade e Instituição Financeira disponíveis para seleção.
- Servidor da aplicação e web responsiva ativos.

## Cenários essenciais

### 1. Cadastro sem documento e isolamento organizacional

1. No contexto da primeira organização, crie uma PF somente com nome.
2. Crie uma PJ somente com razão social.
3. Pesquise ambas por nome e confirme que ficam disponíveis na primeira organização.
4. Troque para a segunda organização e pesquise pelos mesmos nomes.
5. **Esperado**: as Pessoas da primeira organização não são expostas na segunda; novos cadastros podem usar o mesmo CPF/CNPJ de uma Pessoa existente em outra organização.

### 2. Documento, estado e exclusão

1. Cadastre uma PF com CPF válido.
2. Inative-a e tente cadastrar outra PF com o mesmo CPF na mesma organização.
3. Confirme o conflito, reative a primeira Pessoa e solicite exclusão física.
4. Repita a exclusão depois de criar uma referência bloqueante em módulo integrado.
5. **Esperado**: documento continua reservado durante inativação; exclusão livre remove a Pessoa e seus dados filhos; exclusão bloqueada informa o uso conhecido ou falha segura.

### 3. Endereço e contatos

1. Cadastre endereço brasileiro com País, UF e Município selecionados, rua textual sem referência de localidade e número `12A`.
2. Adicione e-mail, telefone, WhatsApp e website válidos.
3. Tente salvar telefone inválido e endereço brasileiro sem Município.
4. **Esperado**: o endereço textual é aceito quando completo; cada erro é apresentado no item e campo correspondente; nenhum contato é considerado principal.

### 4. Conta bancária e Pix

1. Associe uma conta a uma Instituição Financeira corporativa e inclua agência, número e dígitos textuais.
2. Inclua chaves Pix de e-mail, telefone e aleatória.
3. Tente repetir a mesma chave normalizada para a mesma Pessoa.
4. **Esperado**: dados válidos persistem sem marcação de principal/finalidade; repetição na mesma Pessoa é recusada com erro específico.

### 5. Relacionamento direcional

1. Cadastre duas Pessoas na mesma organização.
2. Registre que a primeira é `CHILD_OF` a segunda.
3. Consulte as duas Pessoas e depois exclua fisicamente a primeira.
4. **Esperado**: a primeira mostra “filho(a) de” e a segunda mostra o rótulo oposto; existe apenas um vínculo persistido; a exclusão remove o vínculo e não altera a segunda Pessoa.

### 6. Roundtrip ponta a ponta

1. Na interface responsiva, crie uma Pessoa com endereço, contato e relacionamento.
2. Capture a resposta real da criação e consulte o detalhe pela API da mesma organização.
3. Compare nomes de campos, tipos, estados, coleções e projeção de relacionamento com [people-api.md](contracts/people-api.md).
4. Atualize um dado em desktop e confirme o mesmo resultado em viewport de telefone.
5. **Esperado**: contrato, resposta real e estado observado na web permanecem coerentes, sem perda funcional entre os formatos responsivos.
