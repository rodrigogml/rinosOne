# Cenários de Validação: Área de Trabalho da Aplicação

## Cenário 1: Área inicial neutra

1. Autenticar uma pessoa validada.
2. Acessar a Área de trabalho sem organização selecionada.
3. **Esperado**: a top bar permanece presente; não há conteúdo demonstrativo de segurança nem módulos fictícios; somente destinos pessoais aprovados podem aparecer.

## Cenário 2: Navegação e abertura de superfície

1. Abrir uma categoria do menu lateral.
2. Escolher um destino pessoal autorizado.
3. **Esperado**: o mega menu fecha, o menu lateral recolhe no desktop e a superfície aparece ativa com uma entrada na barra de tarefas.

## Cenário 3: Instâncias e alternância

1. Abrir uma superfície de instância única duas vezes.
2. Abrir duas instâncias de uma superfície que permita múltiplas instâncias.
3. Alternar pelas entradas da barra de tarefas e pelos atalhos documentados.
4. **Esperado**: a instância única só existe uma vez; as duas instâncias múltiplas são distinguíveis; o estado local é preservado em cada alternância.

## Cenário 4: Alterações pendentes

1. Abrir uma superfície que informe alteração pendente.
2. Solicitar seu fechamento.
3. Cancelar a confirmação e tentar novamente, confirmando o descarte.
4. **Esperado**: o primeiro cancelamento preserva a superfície; a confirmação remove somente a instância solicitada e devolve foco de forma previsível.

## Cenário 5: Troca de organização

1. Em uma aba, abrir uma superfície pessoal e uma contextual de organização.
2. Trocar ou encerrar a organização atual.
3. **Esperado**: somente a superfície contextual é fechada; a pessoal continua aberta; outra aba não é modificada.

## Cenário 6: Telefone e acessibilidade

1. Em tela estreita, abrir o painel de navegação e a lista de superfícies.
2. Operar com Tab, setas, Enter e Escape conforme cada controle.
3. Ativar redução de movimento e uma escala visual ampla.
4. **Esperado**: uma superfície ocupa o palco por vez, o foco não alcança camadas bloqueadas e as mudanças permanecem compreensíveis sem animação.
