# Especificação da Feature: Acesso de Usuário

**Feature**: `user-auth`  
**Criada em**: 2026-09-22  
**Status**: Draft

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Acesso web | Web | Visitante e usuário validado | FULL | Cadastro, validação de e-mail, definição opcional de senha, login, encerramento de sessões e invalidação das demais sessões | Perfil, alteração ou recuperação de senha e autenticação de dois fatores |
| Consumidores futuros autorizados | Other | Não definido | DEFERRED | Nenhum nesta fase | Interfaces, produtos, módulos e integrações futuras |

## Clarificações

### Sessão 2026-09-22

- Q: Qual é o prazo de expiração de links e códigos de validação e de acesso sem senha? -> A: 10 minutos.
- Q: Quais são os limites numéricos de emissão e tentativa para cadastro, validação, código e senha? -> A: Usar valores padrão seguros e configuráveis por ambiente.
- Q: Qual é o formato e o limite de erros para o código enviado por e-mail? -> A: Seis dígitos numéricos; após três tentativas incorretas, a emissão é excluída e exige novo envio. O limite é configurável por ambiente.
- Q: Quais critérios exatos de tamanho mínimo e força uma senha deve atender? -> A: Mínimo de 6 caracteres e 2 de 4 categorias.
- Q: Qual é a duração da sessão e como funciona “manter-me conectado”? -> A: Não há expiração por inatividade; a opção cria uma autenticação persistente configurável por ambiente que recria a sessão server-side se ela se perder.
- Q: Invalidar as demais sessões também revoga autenticações persistentes em outros navegadores? -> A: Sim; preservar somente a autenticação persistente associada à sessão atual, quando houver.
- Q: Qual é a retenção de emissões e logs de segurança? -> A: Excluir emissões usadas, vencidas ou substituídas sem guarda; reter logs por 30 dias por padrão, configurável por ambiente.
- Q: Qual é a retenção de sessões invalidadas? -> A: Excluir imediatamente, sem retenção.
- Q: Qual diretriz de privacidade e compliance será entregue nesta fase? -> A: Nenhum entregável formal; o tema é adiado, preservando os controles técnicos de segurança aprovados.

## Cenários de Usuário e Testes

### User Story 1 - Cadastrar e ativar acesso (Prioridade: P1)

Como visitante, quero me cadastrar com meu e-mail e validar que ele me pertence para poder tornar minha conta elegível ao acesso.

**Por que esta prioridade**: sem uma identidade validada, nenhuma outra jornada de acesso pode ocorrer com segurança.

**Teste independente**: um visitante conclui o cadastro, recebe uma mensagem de validação com link e código, confirma o e-mail por um desses meios e informa o nome de exibição obrigatório, obtendo uma conta pronta para autenticação.

**Cenários de aceitação**:

1. **Dado** que sou visitante e informo um e-mail válido ainda não cadastrado, **quando** solicito o cadastro, **então** o sistema cria uma conta não validada e envia uma mensagem de validação com link e código de uso único.
2. **Dado** que possuo uma mensagem de validação ainda válida, **quando** uso seu link ou código e informo meu nome de exibição, **então** o sistema confirma meu e-mail e inicia minha sessão autenticada.
3. **Dado** que meu e-mail não está validado, **quando** tento entrar, **então** o sistema não me concede acesso autenticado.
4. **Dado** que um e-mail já está cadastrado, **quando** tento iniciar um novo cadastro com ele, **então** recebo uma resposta que não revela se a conta existe.

---

### User Story 2 - Entrar sem senha por e-mail (Prioridade: P1)

Como usuário com e-mail validado, quero receber ao mesmo tempo um link mágico e um código de uso único para escolher a forma mais conveniente de entrar sem senha.

**Por que esta prioridade**: a senha é opcional, portanto o acesso sem senha é uma jornada essencial e suficiente para usuários sem senha definida.

**Teste independente**: um usuário validado sem senha solicita o acesso, conclui a autenticação por código ou por link e passa a ter uma sessão autenticada.

**Cenários de aceitação**:

1. **Dado** que meu e-mail está validado, **quando** solicito acesso sem senha, **então** recebo uma única mensagem contendo um link mágico e um código de uso único.
2. **Dado** que recebi a mensagem de acesso, **quando** digito o código correto na página que permaneceu aberta, **então** inicio uma sessão autenticada.
3. **Dado** que recebi a mensagem de acesso, **quando** abro o link mágico em outra aba, **então** inicio uma sessão autenticada nessa aba e continuo a jornada a partir dela.
4. **Dado** que um dos meios de uma emissão foi usado, **quando** tento usar novamente o mesmo meio ou o outro meio daquela emissão, **então** o acesso é recusado.
5. **Dado** que selecionei “Manter-me conectado” antes de solicitar o acesso, **quando** concluo o acesso por código ou link, **então** continuo autenticado após fechar e reabrir o navegador.

---

### User Story 3 - Definir senha e entrar por senha (Prioridade: P2)

Como usuário validado, quero poder definir uma senha e usá-la junto do meu e-mail para entrar, sem perder a alternativa de entrar sem senha.

**Por que esta prioridade**: a senha fornece uma alternativa de acesso para quem desejar utilizá-la, mas não é requisito para a conta existir ou entrar.

**Teste independente**: um usuário validado define uma senha que atende aos critérios e inicia uma sessão usando e-mail e senha.

**Cenários de aceitação**:

1. **Dado** que meu e-mail está validado e ainda não possuo senha, **quando** defino uma senha que atende aos critérios, **então** ela fica disponível para login posterior.
2. **Dado** que tenho uma senha definida, **quando** informo meu e-mail e a senha correta, **então** inicio uma sessão autenticada.
3. **Dado** que não tenho senha definida, **quando** tento usar o login por senha, **então** não obtenho acesso e posso escolher o fluxo sem senha.
4. **Dado** que informo uma senha incorreta, **quando** tento entrar, **então** não obtenho acesso e a resposta não expõe dados adicionais da conta.
5. **Dado** que seleciono “Manter-me conectado” ao entrar por senha, **quando** fecho e reabro o navegador, **então** a autenticação é restaurada sem solicitar novas credenciais.

---

### User Story 4 - Controlar sessões ativas (Prioridade: P2)

Como usuário autenticado, quero encerrar minha sessão atual ou invalidar todas as demais sessões para manter o controle do acesso à minha conta.

**Por que esta prioridade**: o controle de sessões reduz o risco de acesso persistente em dispositivos que o usuário não controla mais.

**Teste independente**: um usuário com sessões em mais de um navegador encerra a sessão atual ou invalida as demais e observa o efeito correspondente em cada sessão.

**Cenários de aceitação**:

1. **Dado** que estou autenticado, **quando** encerro minha sessão atual, **então** perco o acesso autenticado naquele navegador.
2. **Dado** que tenho sessões ou autenticações persistentes em outros navegadores, **quando** invalido todas as demais sessões, **então** permaneço autenticado na sessão atual e as outras deixam de conceder acesso.

### Casos de Borda

- Uma conta sem e-mail validado não pode usar senha, código ou link para acessar o sistema.
- Link ou código inválido, expirado, já utilizado ou substituído por uma emissão posterior não autentica ninguém.
- Repetidas tentativas de código, senha, cadastro ou emissão de acesso sofrem limitação sem revelar a existência de uma conta; a terceira tentativa incorreta de um código descarta sua emissão.
- O usuário pode perder a página de código ou usar o link em outro navegador sem impedir a conclusão válida de uma única emissão.
- A ausência de senha não impede a autenticação sem senha, e uma senha definida não impede essa alternativa.

## Requisitos

### Requisitos Funcionais

- **FR-001**: O sistema DEVE permitir que um visitante inicie o cadastro usando um e-mail válido.
- **FR-002**: O sistema DEVE manter um e-mail associado a no máximo uma conta.
- **FR-003**: O sistema DEVE criar a conta como não validada até que o usuário confirme o controle do e-mail.
- **FR-004**: O sistema DEVE enviar uma mensagem de validação com link e código numérico de seis dígitos, ambos de uso único, após o início do cadastro e permitir a confirmação somente uma vez por emissão.
- **FR-005**: O sistema DEVE exigir um nome de exibição antes de tornar a conta validada elegível à autenticação.
- **FR-006**: O sistema NÃO DEVE conceder acesso autenticado a uma conta cujo e-mail não esteja validado.
- **FR-007**: O sistema DEVE permitir que uma conta validada defina senha de forma opcional.
- **FR-008**: O sistema DEVE aceitar login por e-mail e senha somente para uma conta validada com senha definida e credenciais corretas.
- **FR-009**: O sistema DEVE permitir que uma conta validada solicite acesso sem senha por e-mail.
- **FR-010**: Para cada solicitação de acesso sem senha, o sistema DEVE enviar na mesma mensagem um link mágico e um código numérico de seis dígitos, ambos de uso único e associados à mesma emissão.
- **FR-011**: O sistema DEVE autenticar uma conta validada pela utilização bem-sucedida do link mágico ou do código correspondente.
- **FR-012**: Ao utilizar com sucesso o link ou o código de uma emissão, o sistema DEVE invalidar os dois meios daquela emissão.
- **FR-013**: O sistema DEVE invalidar emissões anteriores quando emitir nova validação ou novo acesso para a mesma finalidade.
- **FR-014**: O sistema DEVE limitar tentativas e emissões de cadastro, validação e autenticação por usuário, e-mail, origem e intervalo de tempo, sem revelar se uma conta existe; os valores padrão DEVEM ser configuráveis por ambiente.
- **FR-014a**: Após o número máximo configurável de códigos incorretos de uma mesma emissão — três por padrão — o sistema DEVE excluir a emissão imediatamente e exigir nova solicitação.
- **FR-015**: O sistema DEVE fornecer mensagens claras para falha de validação, código, link ou credencial, sem expor informações sensíveis nem confirmar a existência de uma conta.
- **FR-016**: O sistema DEVE permitir o encerramento da sessão atualmente usada pelo usuário autenticado.
- **FR-017**: O sistema DEVE permitir que o usuário autenticado invalide todas as suas sessões e autenticações persistentes, exceto a sessão atual e a autenticação persistente associada a ela, quando houver.
- **FR-018**: O sistema DEVE registrar eventos de segurança sem incluir senha, código, link, token ou dados pessoais desnecessários.
- **FR-019**: O sistema DEVE proteger as informações de autenticação em trânsito, em armazenamento e no uso da sessão.
- **FR-020**: O sistema DEVE rejeitar senhas com menos de 6 caracteres ou que não possuam pelo menos 2 destas 4 categorias: letra minúscula, letra maiúscula, caractere especial e número.
- **FR-021-INFRA**: A feature DEVE executar uma rotina periódica simples e configurável para excluir emissões vencidas. A expiração lógica continua ocorrendo exatamente após 10 minutos, independentemente do próximo ciclo de limpeza. Não exige rotação de chaves, processo externo de atualização, mutex entre réplicas, backup específico ou chave de idempotência além das regras funcionais acima.
- **FR-022**: O sistema DEVE expirar links e códigos de validação e de acesso sem senha após 10 minutos da emissão.
- **FR-023**: O sistema DEVE oferecer a opção “Manter-me conectado” no cadastro, no login por senha e na solicitação de acesso por e-mail; a escolha DEVE ser aplicada quando a autenticação for concluída.
- **FR-024**: Quando “Manter-me conectado” estiver selecionado, o sistema DEVE manter uma credencial de autenticação persistente, com duração sem expiração por inatividade por padrão e parâmetros configuráveis por ambiente.
- **FR-025**: Se a sessão server-side de uma autenticação persistente deixar de existir, o sistema DEVE reconstruir uma nova sessão autenticada sem solicitar credenciais novamente.
- **FR-026**: Se “Manter-me conectado” não estiver selecionado, fechar o navegador ou perder a sessão server-side DEVE exigir novo login.
- **FR-027**: O sistema DEVE excluir sem retenção emissões de validação e acesso sem senha que tenham sido usadas, vencidas ou substituídas.
- **FR-028**: O sistema DEVE reter logs de segurança por 30 dias por padrão, com período configurável por ambiente.
- **FR-029**: O sistema DEVE excluir sessões invalidadas imediatamente, sem retenção.
- **FR-030**: Após uma confirmação de e-mail bem-sucedida e o fornecimento do nome de exibição, o sistema DEVE criar uma sessão autenticada e aplicar a escolha prévia de autenticação persistente, quando houver.

### Entidades Principais

- **Conta de usuário**: representa a identidade cadastrada, seu e-mail único, estado de validação, nome de exibição e a possibilidade de possuir ou não uma senha.
- **Emissão de validação**: representa uma oportunidade temporária e única para confirmar que a pessoa controla o e-mail cadastrado.
- **Emissão de acesso sem senha**: representa o link mágico e o código emitidos juntos para uma tentativa de autenticação, incluindo seu estado de uso e validade.
- **Sessão de usuário**: representa um acesso autenticado ativo e permite identificar a sessão atual e invalidar as demais sessões da mesma conta.
- **Autenticação persistente**: representa a escolha “Manter-me conectado”, permite reconstruir uma sessão server-side e pode ser revogada pelo usuário.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-001**: 100% dos cenários aprovados de cadastro exigem validação do e-mail e nome de exibição antes de criar uma sessão autenticada.
- **SC-002**: 100% dos cenários aprovados de acesso sem senha confirmam que o uso de um link ou código invalida toda a emissão correspondente.
- **SC-003**: 100% dos cenários aprovados de controle de sessão confirmam que invalidar as demais sessões preserva somente a sessão que realizou a ação.
- **SC-004**: Nenhum cenário aprovado de erro de cadastro ou autenticação revela a existência de uma conta, senha, código, link ou token.
