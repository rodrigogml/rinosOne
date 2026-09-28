# Guia de Validação — Fundação de Localidades

## Pré-requisitos

- Banco MySQL local configurado para a aplicação.
- Tabelas de fila e cache existentes disponíveis, pois a consulta postal usa tarefa e estado compartilhado.
- Agendador Laravel e trabalhador de fila ativos durante os cenários assíncronos.
- Acesso de Plataforma com a permissão de leitura da rotina IBGE para validar o Hub.

## Cenários essenciais

### 1. Primeira atualização territorial

1. Aplique as migrations da feature em base de teste limpa.
2. Execute o ponto agendado ou a chamada de serviço que avalia se a rotina está devida.
3. Confirme uma execução `RUNNING` seguida de `SUCCEEDED` para `locality-ibge-territory-catalog`.
4. Confirme a criação do País Brasil, das UFs e dos Municípios retornados pelo IBGE.
5. Execute novamente dentro de um mês e confirme que não há nova execução.

### 2. Lock e falha IBGE

1. Mantenha uma execução ativa e invoque uma segunda avaliação simultânea.
2. Confirme que somente uma execução é criada.
3. Simule falha da fonte; confirme que o catálogo anterior não é apagado e o histórico expõe somente resumo/código seguro.
4. Confirme que a próxima tentativa só ocorre depois do atraso configurado de retentativa.

### 3. Consulta local e enriquecimento paralelo

1. Prepare uma referência `ACTIVE` para um CEP brasileiro na base local.
2. Faça `POST /api/v1/localities/postal-references/lookup`.
3. Confirme que a resposta traz o resultado local imediatamente com `refresh.state = PENDING`.
4. Simule ViaCEP lento e BrasilAPI com novo resultado válido; execute o trabalhador de fila.
5. Consulte o estado em até um segundo e confirme o novo candidato, sem detalhe de provedor e sem bloquear o resultado local.

### 4. Supressão e duplicidade

1. Marque uma referência como `REMOVED` e mantenha sua observação de origem.
2. Faça a fonte devolver novamente a mesma identidade/assinatura; confirme que ela não volta a `ACTIVE` nem é exposta.
3. Faça duas fontes devolverem a mesma assinatura forte; confirme uma referência ativa e a redundante removida com `DUPLICATE_AUTOMATIC`.
4. Faça as fontes divergirem em UF ou Município; confirme que ambas permanecem ativas.

### 5. Hub de Manutenções

1. Acesse a lista de rotinas com a permissão de leitura.
2. Confirme título, agenda inicial/mensal, estado e histórico da rotina IBGE.
3. Confirme `canSynchronize = false` e que qualquer tentativa de ação recebe a resposta de rotina indisponível.
