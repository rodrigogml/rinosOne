# Especificação da Feature: Fundação de Localidades

**Feature**: `locality-foundation`
**Criada em**: 2026-09-27
**Status**: Draft

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Hub de Manutenções | Web responsiva administrativa | Administradores da Plataforma autorizados | PARTIAL | Consultar a saúde, agenda, última execução e histórico permitido da atualização territorial. | Disparo manual, edição de agenda, cadastro territorial e gestão de CEPs/logradouros. |
| Consumidor futuro de referência postal | Web responsiva | Pessoas usuárias autenticadas | DEFERRED | Receber candidatos locais imediatamente e atualização segura quando novas referências forem consolidadas. | Cadastro do endereço final, número, complemento e vínculo com um domínio de negócio. |
| Administração de localidades | Web responsiva administrativa | Administradores da Plataforma autorizados | DEFERRED | Consultar, filtrar, remover e reativar referências do catálogo. | Toda a tela e seu padrão de busca serão definidos em feature posterior. |

## Cenários de Usuário e Testes

### User Story 1 - Manter a referência territorial brasileira atualizada (Prioridade: P1)

Como administrador da Plataforma, quero que o catálogo global de Países, UFs e Municípios brasileiros seja atualizado automaticamente por fonte oficial, para que funcionalidades futuras usem referências territoriais estáveis sem cadastro manual.

**Por que esta prioridade**: UF e Município são a âncora oficial para reconciliar referências postais brasileiras.

**Teste independente**: iniciar o sistema com catálogo territorial sem execução prévia, confirmar que a atualização é iniciada automaticamente, e verificar que uma execução posterior dentro de um mês não cria uma segunda atualização.

**Cenários de aceite**:

1. **Dado** que a rotina territorial nunca foi concluída, **quando** o agendador a detectar, **então** ela é iniciada sem ação humana.
2. **Dado** que uma execução territorial foi concluída há menos de um mês, **quando** o agendador a verificar, **então** nenhuma atualização adicional é iniciada.
3. **Dado** que uma UF ou Município mantém o mesmo código oficial e tem seu nome atualizado, **quando** a fonte oficial publicar a alteração, **então** a referência global existente permanece a mesma e apresenta o nome atualizado.
4. **Dado** que duas execuções territoriais são solicitadas simultaneamente, **quando** a primeira estiver em curso, **então** a outra é recusada sem criar uma segunda execução concorrente.

---

### User Story 2 - Consultar referências postais sem bloquear a jornada (Prioridade: P1)

Como pessoa usuária de uma futura funcionalidade de endereço, quero receber referências locais de CEP imediatamente enquanto o sistema busca informações adicionais, para prosseguir sem esperar por serviços externos.

**Por que esta prioridade**: a indisponibilidade ou lentidão de uma fonte externa não pode impedir a continuidade de uma operação de negócio.

**Teste independente**: consultar um CEP com referências locais e fontes externas lentas; confirmar que os candidatos locais são apresentados de imediato, a atualização é sinalizada de modo neutro e a consulta pode ser encerrada sem cancelar o enriquecimento.

**Cenários de aceite**:

1. **Dado** um CEP com candidatos locais, **quando** uma funcionalidade consumidora o consultar, **então** os candidatos locais ficam disponíveis antes da conclusão das consultas externas.
2. **Dado** que a busca por novos dados está pendente, **quando** a pessoa usuária permanece na funcionalidade consumidora, **então** ela recebe indicação neutra de atualização e vê a lista atualizada após a consolidação.
3. **Dado** que a pessoa usuária selecionou um candidato local e avançou, **quando** a consolidação externa ainda estiver em andamento, **então** a operação da pessoa usuária continua e o enriquecimento conclui sem depender da tela aberta.
4. **Dado** que uma ou mais fontes externas falharem, **quando** ainda houver candidatos locais ou respostas de outras fontes, **então** os resultados disponíveis permanecem utilizáveis e a pessoa usuária não recebe detalhes técnicos da falha.

---

### User Story 3 - Consolidar referências postais de múltiplas fontes (Prioridade: P1)

Como responsável pela qualidade do catálogo, quero que o sistema reúna dados retornados pelas fontes habilitadas sem confundir CEP com identidade de logradouro, para que a base cresça sem esconder resultados legítimos.

**Por que esta prioridade**: uma mesma referência postal pode ter vários candidatos, e fontes diferentes podem divergir em texto, bairro ou cobertura.

**Teste independente**: consultar um CEP para o qual fontes retornam múltiplos candidatos, incluindo uma duplicidade comprovada e uma divergência territorial; confirmar que somente a duplicidade comprovada é removida e que a divergência continua selecionável.

**Cenários de aceite**:

1. **Dado** um CEP sem referência local suficiente, **quando** as fontes habilitadas responderem, **então** cada resultado válido é incorporado e pode ser selecionado.
2. **Dado** que fontes retornam a mesma referência com equivalência comprovada, **quando** a consolidação terminar, **então** uma referência fica disponível e as redundantes ficam removidas com justificativa de duplicação automática.
3. **Dado** que fontes retornam referências parecidas, mas com conflito de UF ou Município, **quando** a consolidação terminar, **então** o sistema não as remove automaticamente.
4. **Dado** que um CEP representa somente município, bairro, faixa ou outra referência sem logradouro específico, **quando** ele for incorporado, **então** o sistema não inventa um logradouro nem uma precisão inexistente.

---

### User Story 4 - Respeitar remoções administrativas do catálogo (Prioridade: P2)

Como administrador da Plataforma, quero que uma referência removida permaneça fora dos resultados automáticos até que eu a reative explicitamente em uma futura administração de localidades, para que dados duplicados, inválidos ou obsoletos não reapareçam a cada consulta.

**Por que esta prioridade**: a fonte externa não pode desfazer uma decisão de qualidade tomada pela Plataforma.

**Teste independente**: marcar uma referência como removida, realizar nova consulta que a fonte volte a devolver e confirmar que ela continua ausente da lista; reativá-la na futura administração e confirmar que volta a ser elegível.

**Cenários de aceite**:

1. **Dado** uma referência marcada como removida, **quando** uma fonte retornar novamente a mesma identidade externa ou assinatura segura, **então** o sistema a ignora antes de exibir candidatos.
2. **Dado** uma referência removida por duplicação, invalidez ou obsolescência, **quando** uma pessoa usuária consultar o contexto correspondente, **então** ela não vê a referência removida.
3. **Dado** uma reativação administrativa futura, **quando** ela for confirmada, **então** a referência pode voltar a ser exibida nas consultas posteriores.

---

### User Story 5 - Acompanhar a atualização territorial no Hub (Prioridade: P2)

Como administrador da Plataforma, quero acompanhar a rotina de atualização territorial na Central de Manutenções, para saber se o catálogo oficial está pronto, em execução ou com falha sem poder violar as regras da rotina.

**Por que esta prioridade**: a manutenção é global e deve ser observável, mas sua execução não deve depender de ações manuais.

**Teste independente**: acessar o Hub com permissão de leitura e confirmar a presença da rotina, agenda mensal, estado e histórico; confirmar que não existe ação de sincronização manual.

**Cenários de aceite**:

1. **Dado** um administrador autorizado, **quando** abrir a Central de Manutenções, **então** ele encontra a rotina territorial, seu estado, sua agenda e o histórico permitido.
2. **Dado** uma rotina sem execução anterior, **quando** o administrador a consultar, **então** o Hub apresenta a condição sem classificá-la como falha.
3. **Dado** um administrador autorizado, **quando** consultar a rotina territorial, **então** não encontra ação para dispará-la manualmente.
4. **Dado** que uma execução falhou, **quando** o administrador consultar seu histórico, **então** ele vê uma informação segura sobre a falha sem segredos ou detalhes de provedor.

### Casos de Borda

- Uma fonte externa pode devolver CEP inexistente, resposta vazia, conteúdo incompleto ou resultados múltiplos; nenhum desses casos cria precisão artificial.
- A indisponibilidade total dos provedores externos não apaga nem impede o uso de referências locais existentes.
- A ausência de uma referência na resposta de uma fonte não a remove automaticamente do catálogo.
- Uma referência removida deve continuar suprimida mesmo se for reencontrada em uma consulta posterior.
- Um Município brasileiro só é vinculado quando puder ser reconciliado com a referência oficial correspondente; textos observados são preservados para os demais casos.
- Países e localidades internacionais devem poder ser representados sem exigir a estrutura brasileira de UF e Município.
- Uma falha ou suspensão da rotina IBGE não pode gerar exclusão automática de UFs ou Municípios previamente conhecidos.

## Requisitos

### Requisitos Funcionais

- **FR-LOC-001**: O sistema DEVE manter um catálogo territorial global de Países, UFs e Municípios, reutilizável por todos os tenants.
- **FR-LOC-002**: O sistema DEVE identificar cada entidade canônica do catálogo por identificador técnico imutável `BIGINT`; códigos externos são atributos de negócio.
- **FR-LOC-003**: Países DEVEM preservar códigos ISO alpha-2 e alpha-3 e Município brasileiro DEVE preservar seu código oficial IBGE.
- **FR-LOC-004**: O core NÃO DEVE referenciar entidades de tenant; referências futuras de tenant para o catálogo global DEVEM seguir a política de integridade não restritiva já definida pela Plataforma.
- **FR-LOC-005**: O sistema NÃO DEVE criar catálogo de nacionalidades nesta feature.
- **FR-LOC-006**: O sistema DEVE manter CEP como código postal e NÃO DEVE usá-lo como identidade única de referência de logradouro.
- **FR-LOC-007**: O sistema DEVE permitir que uma referência postal esteja associada a zero, uma ou várias referências de logradouro e que uma referência de logradouro esteja associada a vários CEPs.
- **FR-LOC-008**: O sistema DEVE preservar dados textuais observados e origem de cada referência incorporada, sem exigir que duas grafias tenham a mesma identidade.
- **FR-LOC-009**: A atualização territorial oficial DEVE iniciar automaticamente quando não houver execução anterior concluída e DEVE verificar atualizações mensais depois disso.
- **FR-LOC-010**: A atualização territorial oficial DEVE executar como singleton e recusar execução simultânea.
- **FR-LOC-011**: A rotina territorial DEVE aparecer no Hub de Manutenções para administradores autorizados, com estado, agenda, última execução e histórico técnico permitido.
- **FR-LOC-012**: A rotina territorial NÃO DEVE oferecer disparo manual, edição manual de dados importados nem cadastro manual de UFs e Municípios.
- **FR-LOC-013**: Uma consulta de CEP DEVE disponibilizar os resultados locais imediatamente e iniciar, sem bloquear a pessoa usuária, a busca de dados novos nas fontes habilitadas.
- **FR-LOC-014**: O sistema DEVE consultar ViaCEP e BrasilAPI em paralelo quando estiverem habilitadas e consolidar suas respostas em um contrato interno comum.
- **FR-LOC-015**: Enquanto houver enriquecimento pendente, a funcionalidade consumidora DEVE poder acompanhar a atualização em intervalo de até um segundo e interromper somente sua observação visual ao fechar ou avançar no fluxo.
- **FR-LOC-016**: O enriquecimento iniciado por uma consulta DEVE concluir mesmo que a pessoa usuária tenha encerrado a tela ou avançado no fluxo que iniciou a consulta.
- **FR-LOC-017**: Todo resultado válido retornado por fonte habilitada DEVE ficar disponível para seleção, exceto quando já estiver removido ou for classificado como duplicidade comprovada.
- **FR-LOC-018**: O sistema DEVE remover automaticamente uma referência somente quando uma regra de equivalência determinística e específica da fonte comprovar duplicidade; sem essa comprovação, candidatos divergentes DEVEM coexistir ativos.
- **FR-LOC-019**: O catálogo DEVE possuir somente os estados `ACTIVE` e `REMOVED`; a justificativa da remoção pode distinguir duplicação automática, duplicação administrativa, invalidez, obsolescência ou outro motivo sem alterar o comportamento do estado.
- **FR-LOC-020**: Uma referência `REMOVED` NÃO DEVE ser exibida nem reativada automaticamente por retorno posterior de fonte externa; a reativação cabe exclusivamente à futura administração autorizada de localidades.
- **FR-LOC-021**: Falhas, lentidão ou ausência de resposta de uma fonte externa NÃO DEVEM impedir o uso de referências locais ou de resultados válidos de outras fontes.
- **FR-LOC-022**: A pessoa usuária NÃO DEVE receber nome de provedor, credenciais, detalhes técnicos ou conteúdo bruto de erros externos.
- **FR-LOC-023**: O sistema DEVE registrar logs técnicos seguros das cargas e consultas externas, sem criar tabela de auditoria exclusiva de cargas.
- **FR-LOC-024**: O Hub DEVE manter o histórico técnico da rotina territorial conforme o prazo configurável de retenção já definido para manutenções da Plataforma. Como esta rotina não admite ação administrativa, ela não gera auditoria administrativa própria nesta feature.
- **FR-LOC-INFRA-SCHED**: A política de agenda da atualização territorial é `auto`: quando não houver execução concluída, a rotina é imediatamente devida; depois, torna-se devida mensalmente. O Hub apresenta seu estado, mas não define nem altera a agenda.
- **FR-LOC-INFRA-IDEMP**: Repetições da atualização territorial e da incorporação de uma mesma referência externa DEVEM produzir o mesmo catálogo lógico, sem multiplicar referências equivalentes.

### Entidades Principais

- **País**: referência territorial global, identificada tecnicamente e reconhecida por códigos ISO; pode ser usada por localidades e futuros endereços internacionais.
- **UF**: unidade federativa brasileira global, com código e sigla oficiais; pertence ao País Brasil.
- **Município**: referência territorial brasileira global, vinculada a uma UF e identificada por código IBGE.
- **CEP**: código postal associado a um País e potencialmente a várias referências postais; não é sinônimo de endereço nem de logradouro.
- **Referência de logradouro**: localidade postal reutilizável, com texto canônico quando conhecido, município quando reconciliado e múltiplas observações de origem possíveis.
- **Observação de referência**: texto e atributos recebidos de uma fonte, com origem e identificador externo quando disponível; sustenta busca, reconciliação e supressão segura.
- **Rotina territorial IBGE**: manutenção automática que atualiza referências oficiais de Países, UFs e Municípios e apresenta seu estado permitido no Hub.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-LOC-001**: 100% das UFs e Municípios retornados pela fonte oficial em uma execução concluída são representados por uma referência global associada ao respectivo código oficial.
- **SC-LOC-002**: 100% das consultas de CEP com referências locais disponibilizam esses candidatos sem aguardar a conclusão dos provedores externos.
- **SC-LOC-003**: 100% das referências marcadas como `REMOVED` nos cenários de teste permanecem ausentes de consultas posteriores, inclusive quando a mesma fonte as devolve novamente.
- **SC-LOC-004**: 100% dos cenários de duplicidade comprovada removem somente redundâncias, e 100% dos cenários com conflito territorial preservam os candidatos distintos.
- **SC-LOC-005**: 100% das execuções concorrentes da rotina territorial testadas resultam em, no máximo, uma execução em curso.
- **SC-LOC-006**: 100% dos administradores autorizados conseguem consultar estado e histórico permitido da rotina territorial, sem receber ação de disparo manual.
