# Quickstart de Validação: Fundação de Tenants

## Cenário 1: Criação e ativação bem-sucedidas

1. Autenticar uma pessoa com e-mail validado.
2. Gerar uma chave de intenção e solicitar a criação de um tenant com nome válido.
3. Consultar a lista de tenants até a preparação ser concluída.
4. **Esperado**: há exatamente um tenant associado como `OWNER`; ele só fica selecionável depois de `ACTIVE`.

## Cenário 2: Reenvio idempotente

1. Autenticar uma pessoa com e-mail validado.
2. Enviar duas vezes a mesma criação com a mesma chave de intenção.
3. Consultar a lista de tenants e a operação de preparação.
4. **Esperado**: ambas as respostas apontam para o mesmo tenant e a mesma preparação; não há schema ou associação duplicados.

## Cenário 3: Tenant indisponível não cria contexto

1. Criar um tenant cuja preparação ainda não tenha terminado ou esteja em falha.
2. Solicitar o início de contexto para seu identificador.
3. **Esperado**: a operação é negada com resposta segura; nenhum módulo contextual nem estado parcial aparecem na aba.

## Cenário 4: Isolamento entre abas

1. Criar e ativar dois tenants para a mesma pessoa autenticada.
2. Na primeira aba, iniciar o contexto do tenant A.
3. Na segunda aba, iniciar o contexto do tenant B.
4. Trocar o contexto da primeira aba ou encerrá-lo.
5. **Esperado**: a segunda aba continua no tenant B; recursos pessoais permanecem disponíveis nas duas abas e nenhum dado contextual é compartilhado.

## Cenário 5: Desabilitação

1. Com um tenant ativo, a pessoa proprietária solicita sua desabilitação.
2. Tenta iniciar novo contexto e executar uma nova ação contextual.
3. **Esperado**: o tenant deixa de ser selecionável e novas ações são negadas, sem excluir sua identidade ou dados.

## Cenário 6: Roundtrip End-to-End

1. Iniciar backend, interface e worker de fila locais com o mesmo banco de teste.
2. Autenticar uma pessoa validada e criar um tenant por `POST /api/v1/tenants` com uma chave de intenção.
3. Capturar a resposta real e verificar os campos `tenant` e `provisioning` contra [tenant-context.md](contracts/tenant-context.md).
4. Aguardar o provisionamento, obter o tenant em `GET /api/v1/tenants` e selecionar por `POST /api/v1/tenants/{tenantId}/contexts`.
5. Confirmar que a interface consome o payload sem coerção silenciosa, identifica o tenant e mantém os recursos pessoais.
6. **Esperado**: payload real, contrato, tipos da interface e estado observável da aba são coerentes.

## Cenário 7: Interação humana crítica

1. Em computador e telefone, abrir o seletor de tenant na barra autenticada.
2. Criar ou escolher um tenant ativo e confirmar a seleção.
3. Abrir outra aba e escolher um tenant diferente; depois encerrar somente o contexto da primeira.
4. **Esperado**: foco, leitura por tecnologia assistiva, toque e teclado permitem concluir a jornada; o tenant ativo fica identificável e nenhuma aba altera a outra.
