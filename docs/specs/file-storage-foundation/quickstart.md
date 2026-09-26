# Cenários de validação — Fundação de armazenamento de arquivos

## Conteúdo deduplicado com posses independentes

1. Armazene a mesma imagem para dois usuários distintos.
2. Confirme dois registros de posse e um único `file_fileContent` e objeto físico ativo.
3. Confirme que o consumo lógico é contabilizado para ambos os usuários.

## Ramificação e lixeira

1. Faça A e B possuírem a mesma versão inicial.
2. Crie uma edição de B a partir da versão inicial e marque-a como atual apenas para B.
3. Crie uma edição de A a partir da mesma versão inicial e confirme que a árvore preserva as duas ramificações.
4. Mova a posse de A para lixeira e valide que ela ainda consome cota.
5. Libere a lixeira de A e confirme que a posse de B e seus bytes permanecem legíveis.

## Retenção, compressão e reconciliação

1. Configure retenção de backup maior que a retenção padrão e valide que a data efetiva de exclusão respeita a maior delas.
2. Altere uma política de compressão e processe um conteúdo elegível; confirme a troca atômica da representação sem alterar a hash lógica.
3. Crie um objeto físico sem confirmação no banco e valide que a reconciliação só o marca ou remove depois do prazo de órfão.

## Integração de recurso gerenciado

1. Associe um avatar a um usuário por `USER_PROFILE_AVATAR`.
2. Substitua-o e valide uma única binding ativa, consumo `SYSTEM_MANAGED` correto e liberação da posse anterior.

## Operação em homologação

Consulte [Operação — Fundação de armazenamento de arquivos](../../operations/file-storage.md) para as variáveis de ambiente, workers, scheduler, retenção, reconciliação e recuperação segura.
