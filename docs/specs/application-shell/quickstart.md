# Cenários de Validação: Casca da Aplicação Autenticada

## Cenário 1: Barra e avatar de fallback

1. Iniciar a aplicação com uma sessão autenticada cujo nome de exibição tenha duas palavras.
2. Abrir a área autenticada em desktop.
3. **Esperado**: a barra superior única mostra a marca reduzida à esquerda e o avatar à direita com as iniciais da primeira e da última palavra.
4. Repetir com nome único de duas letras ou mais, nome único de uma letra e nome ausente.
5. **Esperado**: o avatar apresenta, respectivamente, primeira letra maiúscula seguida da segunda minúscula, a letra maiúscula isolada e `?`.

## Cenário 2: Menu pessoal e saída

1. Iniciar com sessão autenticada e abrir o avatar por teclado.
2. **Esperado**: o menu pessoal abre com foco previsível, contém “Configurações do usuário” como destino reservado e separa visualmente seus utilitários inferiores.
3. Abrir preferências visuais e selecionar uma opção; abrir idioma e selecionar outro idioma.
4. **Esperado**: a sessão e o conteúdo autenticado permanecem ativos, e a escolha é aplicada pelos controles já existentes.
5. Acionar o encerramento da sessão.
6. **Esperado**: a aplicação realiza a saída pelo fluxo de autenticação existente e apresenta o acesso público.

## Cenário 3: Navegação móvel

1. Iniciar com sessão autenticada em largura de telefone.
2. Acionar a identidade reduzida na barra.
3. **Esperado**: um painel de navegação é aberto pelo lado esquerdo sem links de produto fictícios.
4. Fechar o painel por Escape, pelo controle de fechamento e por interação fora dele quando aplicável.
5. **Esperado**: o conteúdo autenticado original continua presente, sem rolagem horizontal, e o foco volta ao acionador adequado.

## Cenário 4: Roundtrip de sessão e saída

1. Iniciar o backend e a interface localmente com uma sessão real autenticada.
2. Carregar o estado da sessão pela operação já documentada de consulta de sessão.
3. Confirmar que o nome de exibição recebido é usado pela barra sem alterar o formato do payload existente.
4. Encerrar a sessão pelo menu pessoal.
5. Consultar novamente o estado de sessão.
6. **Esperado**: a sessão deixa de ser elegível para a área autenticada, e não há divergência entre o payload existente, o tipo consumido e o estado visual final.

## Cenário 5: Acessibilidade e preferências extremas

1. Testar desktop, tablet e telefone nos temas claro e escuro, com escalas compactas e confortáveis.
2. Abrir e fechar menu pessoal e painel móvel por teclado e toque.
3. Ativar preferência de redução de movimento.
4. **Esperado**: controles mantêm foco visível, rótulos acessíveis, área de toque adequada, conteúdo rolável sem corte e nenhuma animação necessária para compreender ou concluir uma ação.
