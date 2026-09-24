# Cenários de Validação: Identidade Visual e Preferências de Interface

## Scenario 1: Entrada de acesso no tema claro e escuro

1. Abrir a entrada de acesso em uma primeira visita com o dispositivo configurado para tema claro.
2. **Expected**: a tela usa tema claro, mostra o logotipo sem moldura acima do cartão e mantém o conjunto centralizado.
3. Abrir as preferências visuais e selecionar o tema escuro.
4. **Expected**: a tela usa tema escuro, preserva e-mail, senha e a opção “Manter-me conectado”, e os controles continuam legíveis.
5. Fechar e reabrir o navegador.
6. **Expected**: o tema escuro escolhido é aplicado antes da tela se tornar visível.

## Scenario 2: Jornada de entrada com e sem senha

1. Abrir a entrada de acesso com e-mail válido e senha vazia.
2. **Expected**: a ação principal é “Entrar sem senha”.
3. Informar uma senha.
4. **Expected**: a ação principal muda para “Entrar”.
5. Acionar “Criar conta”.
6. **Expected**: a pessoa chega a uma tela própria com nome de exibição, e-mail e ação “Criar conta”, mantendo a mesma identidade visual e controles globais.

## Scenario 3: Troca de idioma sem perda de jornada

1. Na criação de conta, preencher nome de exibição e um e-mail válido, sem informar senha ou segredo.
2. Abrir o seletor de idioma e escolher inglês, espanhol e francês, um por vez.
3. **Expected**: cada escolha exibe os textos do idioma escolhido, mantém a rota, os valores seguros do formulário e os controles de tema e idioma.
4. Reabrir a aplicação depois de escolher francês.
5. **Expected**: o francês é restaurado; uma mensagem sem tradução, se houver, aparece em português do Brasil de forma compreensível.

## Scenario 4: Preferências de densidade e acessibilidade

1. Selecionar a maior escala de fonte, espaçamento confortável e componentes amplos.
2. Visitar entrada, criação de conta, confirmação por e-mail e área autenticada em telefone, tablet e computador.
3. **Expected**: não há rolagem horizontal, sobreposição impeditiva ou controle inalcançável.
4. Operar todos os controles por teclado e confirmar foco visível e mensagens compreensíveis sem depender apenas de cor.
5. Ativar “reduzir movimento” no sistema e repetir a mudança de tema e a abertura de preferências visuais.
6. **Expected**: transições não essenciais são reduzidas ou suprimidas.

## Scenario 5: Roundtrip de interação humana

1. Iniciar pela entrada de acesso usando o tema e idioma selecionados.
2. Informar e-mail válido sem senha e solicitar acesso sem senha.
3. **Expected**: a emissão real de acesso é iniciada e a tela de confirmação preserva os tokens, o idioma e as preferências selecionadas.
4. Concluir a confirmação pelo fluxo real já existente.
5. **Expected**: o estado autenticado é apresentado com a mesma preferência visual, sem alteração do contrato de acesso ou perda de sessão.
