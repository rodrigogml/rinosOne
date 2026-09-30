# Quickstart: Interface unificada de acessos e permissões

## Cenário 1: Atribuir papel no tenant ativo

1. Um administrador abre “Usuários e acessos” na Área de Trabalho de um tenant.
2. A interface chama `GET /api/v1/tenants/{tenantId}/authorization/context` e apresenta o nome do tenant de forma persistente.
3. O administrador localiza uma pessoa, abre seu detalhe, escolhe um papel ativo do catálogo e confirma a atribuição.
4. A interface envia o comando pelo prefixo do tenant e reconsulta o resumo da pessoa.
5. **Esperado**: a atribuição aparece como fonte de acesso naquele tenant; não é exibida nem aplicável em outro tenant.

## Cenário 2: Impedir remoção do último administrador

1. O único administrador direto de um tenant abre seu detalhe de acesso.
2. Seleciona remover o papel administrativo e confirma o contexto e o impacto.
3. A API responde `409` com código de invariante de administração final.
4. **Esperado**: a UI mantém os dados, informa que outro administrador deve ser indicado primeiro e não apresenta sucesso.

## Cenário 3: Consultar origem de um acesso

1. Um administrador abre uma pessoa que possui um papel por grupo e uma concessão temporária.
2. A interface chama `GET .../subjects/{subjectId}/effective-access` e apresenta as fontes separadamente.
3. O administrador seleciona uma capacidade e solicita explicação detalhada.
4. **Esperado**: a resposta informa decisão, origem e vigência visíveis, sem revelar informação de outro contexto ou fator confidencial.

## Cenário 4: Compartilhar pasta do workspace pessoal

1. O responsável pelo workspace pessoal abre o painel de compartilhamento de uma pasta.
2. A interface apresenta o workspace responsável, os compartilhamentos diretos e as heranças permitidas para visualização.
3. O responsável adiciona uma pessoa com leitura e confirma alvo, contexto e nível de acesso.
4. **Esperado**: a relação é criada somente para o recurso selecionado; a pessoa não recebe propriedade do item nem acesso a recursos fora do compartilhamento.

## Cenário 5: Contexto alterado durante edição

1. Um administrador inicia a atribuição de um papel no tenant A.
2. Em outra aba ou por alteração de workspace, o contexto ativo muda para tenant B antes da confirmação.
3. A tentativa de confirmação apresenta versão/contexto desatualizado e recarrega o contexto.
4. **Esperado**: nenhuma atribuição é feita em A ou B sem que o administrador confirme novamente o alvo e o contexto correto.

## Cenário 6: Roundtrip end-to-end

1. Iniciar a aplicação local com backend Laravel e Vite disponíveis.
2. Autenticar um administrador em um tenant de teste e abrir a superfície de acessos pelo workspace real.
3. Capturar as respostas reais de `context`, `subjects`, `roles` e `effective-access`.
4. Comparar nomes de campos, tipos, enums e BIGINTs com `contracts/contextual-access-administration-api.md` e com os parsers TypeScript.
5. Executar uma atribuição real autorizada e reconsultar a projeção; depois validar a operação protegida associada à permissão.
6. **Esperado**: sem divergência de shape entre API, contrato e parser; o estado visual e a decisão real de autorização permanecem coerentes.

## Cenário 7: Roundtrip de interação humana

1. Em viewport desktop e telefone, abrir a mesma Área de Trabalho contextual.
2. Concluir o fluxo “quem tem acesso?” e o fluxo “o que esta pessoa pode fazer?” exclusivamente por teclado e, em telefone, por toque.
3. Simular indisponibilidade de rede antes da confirmação de uma escrita.
4. **Esperado**: a interface preserva dados digitados não sensíveis, não envia mutação offline, anuncia erro/sucesso de forma acessível e não expõe controles não autorizados.

## Cenário 8: Meta de desempenho de projeções

1. Preparar em homologação um contexto de tenant com até 1.000 sujeitos ativos e catálogo de papéis compatível.
2. Executar repetidamente as consultas reais `context`, `subjects` e `roles` com página padrão de 25 itens, registrando a distribuição de tempo de resposta.
3. Executar repetidamente `effective-access` e `explain` para um sujeito com fontes múltiplas autorizadas.
4. **Esperado**: o p95 das projeções paginadas não ultrapassa 500 ms e o p95 de acesso efetivo/explicação não ultrapassa 1 s; qualquer desvio cria tarefa de otimização rastreável.
