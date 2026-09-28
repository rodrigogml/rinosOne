# Especificação da Feature: Cadastro de Pessoas por Organização

**Feature**: `person-registration`  
**Criada em**: 2026-09-28  
**Status**: Pronta para planejamento

## Contexto e limite de escopo

O Cadastro de Pessoas permite que cada organização mantenha seus próprios registros de pessoas físicas e jurídicas. Um cadastro de Pessoa pertence somente à organização em que foi criado e não é compartilhado, pesquisável ou reutilizável por outra organização.

Esta feature utiliza os catálogos corporativos de instituições financeiras e localidades somente como referências de seleção. Endereços finais, contatos, contas, chaves Pix e relacionamentos pertencem ao cadastro da Pessoa na organização.

Ficam fora desta feature: dados fiscais, dados profissionais, cadastro de dependentes, definição de contato principal, definição de chave Pix principal ou por finalidade e importação de cadastros de fontes anteriores.

> Decisões de infraestrutura: a limpeza de eventos de auditoria é diária, com retenção configurável por ambiente e padrão de 90 dias. A feature usa as políticas gerais de paginação, idempotência, limite de requisição e limitação de taxa da API.

## Clarificações

### Sessão 2026-09-28

- P: A experiência móvel deve permitir todas as operações da feature ou apenas consulta e operações básicas de Pessoa? → R: Todas as operações da feature devem estar disponíveis integralmente em interface responsiva.
- P: Ao excluir fisicamente uma Pessoa participante de um relacionamento, o relacionamento deve ser removido integralmente ou preservado sem a Pessoa excluída? → R: O relacionamento deve ser removido; a outra Pessoa permanece inalterada.
- P: Como a direção e a semântica oposta de um relacionamento devem ser representadas? → R: Há um único relacionamento direcional. Tipos padronizados possuem um tipo oposto definido, usado como rótulo ao consultar a outra Pessoa; `OUTROS` é o próprio oposto.
- P: Como dados pessoais e financeiros devem ser protegidos e apresentados? → R: A criptografia em repouso é responsabilidade exclusiva da infraestrutura de banco. A aplicação não cifra campos nem mascara CPF, CNPJ, contas ou Pix; somente senhas são mascaradas.
- P: Qual é a retenção dos eventos de auditoria de Pessoas? → R: 90 dias por padrão, sempre configurável por ambiente. A consulta visual de auditoria fica fora desta feature.
- P: Como tratar duas edições simultâneas da mesma Pessoa? → R: A segunda gravação é recusada quando os dados tiverem mudado desde a leitura; o usuário é informado para recarregar e refazer sua alteração, sem mesclagem.
- P: Quais políticas gerais de paginação e operação a feature deve consumir? → R: Paginação geral por página, padrão 50 e máximo configurável 200; demais limites operacionais são políticas globais reutilizadas e não exceções de Pessoas.

## Cobertura de interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Exclusões ou adiamentos |
| --- | --- | --- | --- | --- | --- |
| Cadastro organizacional | Web para desktop | Usuário autorizado da organização | FULL | Listar, buscar, consultar, cadastrar, alterar, duplicar, inativar, reativar e excluir Pessoas; administrar seus dados relacionados. | Dados fiscais, profissionais e dependentes. |
| Cadastro organizacional responsivo | Web móvel | Usuário autorizado da organização | FULL | Executar integralmente as mesmas operações funcionais previstas para o cadastro organizacional, adaptadas à área disponível. | Dados fiscais, profissionais e dependentes. |

## Cenários de usuário e testes

### História 1 — Cadastrar e localizar uma Pessoa da organização (Prioridade: P1)

Como usuário autorizado de uma organização, quero criar e localizar Pessoas físicas ou jurídicas da minha organização para usá-las em seus processos de negócio.

**Por que esta prioridade**: identidade, busca e isolamento organizacional formam o núcleo útil do cadastro.

**Teste independente**: criar uma PF e uma PJ, inclusive uma sem documento, e localizá-las por nome e documento no contexto da organização atual.

**Cenários de aceite**:

1. **Dado** um usuário autorizado no contexto de uma organização, **quando** cadastrar uma PF com nome e sem CPF, **então** o cadastro é aceito e fica disponível somente naquela organização.
2. **Dado** uma PJ sem CNPJ, **quando** seus dados obrigatórios forem preenchidos, **então** o cadastro é aceito.
3. **Dado** um CPF ou CNPJ já usado por uma Pessoa ativa ou inativa da organização, **quando** alguém tentar reutilizá-lo, **então** o sistema recusa o novo cadastro e informa o conflito.
4. **Dado** uma Pessoa de outra organização com o mesmo CPF ou CNPJ, **quando** a Pessoa for cadastrada na organização atual, **então** não há conflito entre organizações.
5. **Dado** uma Pessoa cadastrada, **quando** nome ou apelido/nome fantasia for alterado, **então** seu nome de exibição é atualizado conforme a regra vigente.

---

### História 2 — Manter endereço e contatos da Pessoa (Prioridade: P1)

Como usuário autorizado, quero registrar um ou mais endereços e contatos para uma Pessoa, inclusive quando a rua ainda não constar no catálogo de localidades.

**Por que esta prioridade**: esses dados tornam o cadastro imediatamente utilizável em comunicações e operações organizacionais.

**Teste independente**: cadastrar uma Pessoa com endereço brasileiro contendo rua textual não catalogada, e contatos de tipos distintos; consultar novamente os dados persistidos.

**Cenários de aceite**:

1. **Dado** um novo endereço, **quando** o país for informado e, para endereço brasileiro, UF e município forem vinculados, **então** o usuário pode informar a rua mesmo sem uma referência prévia de localidade.
2. **Dado** uma localização reconhecida, **quando** o usuário a selecionar, **então** o endereço pode utilizá-la sem deixar de preservar os dados necessários para identificar o endereço informado.
3. **Dado** um endereço, **quando** o número for informado como `S/N`, `12A` ou outra forma textual válida, **então** o valor é preservado como informado.
4. **Dado** um contato de e-mail, telefone, celular ou WhatsApp, **quando** o valor for inválido para o seu tipo, **então** o sistema impede o salvamento e identifica o campo que requer correção.
5. **Dado** uma Pessoa com vários contatos, **quando** os tipos e valores forem informados, **então** todos ficam disponíveis sem exigir ou inferir um contato principal.

---

### História 3 — Manter dados bancários e chaves Pix (Prioridade: P2)

Como usuário autorizado, quero registrar contas bancárias e chaves Pix de uma Pessoa para utilizá-las quando um processo organizacional precisar dessas informações.

**Por que esta prioridade**: as informações são relevantes para operações financeiras, mas o cadastro de Pessoa continua útil sem elas.

**Teste independente**: adicionar, alterar e inativar uma conta bancária e uma chave Pix de cada tipo permitido para uma Pessoa existente.

**Cenários de aceite**:

1. **Dado** uma conta bancária, **quando** os dados mínimos compatíveis com o tipo de conta forem informados, **então** ela pode ser associada à Pessoa.
2. **Dado** uma instituição financeira disponível no catálogo corporativo, **quando** ela for selecionada, **então** a conta fica vinculada a essa instituição sem criar uma cópia da instituição na organização.
3. **Dado** uma chave Pix de CPF, CNPJ, e-mail, telefone ou aleatória, **quando** o valor não atender à regra do tipo escolhido, **então** o sistema impede o salvamento e explica a inconsistência.
4. **Dado** várias contas ou chaves Pix válidas, **quando** forem cadastradas, **então** nenhuma delas é marcada como principal ou recebe finalidade nesta fase.

---

### História 4 — Relacionar Pessoas da organização (Prioridade: P2)

Como usuário autorizado, quero registrar o relacionamento entre duas Pessoas da organização para representar vínculos familiares, contratuais ou organizacionais sem criar um cadastro separado de dependentes.

**Por que esta prioridade**: uma relação genérica cobre dependência, parentesco e vínculos de trabalho sem restringir o domínio a um único caso.

**Teste independente**: criar relacionamentos PF–PF, PF–PJ, PJ–PF e PJ–PJ, com tipo e descrição; consultar cada Pessoa e seus vínculos.

**Cenários de aceite**:

1. **Dado** duas Pessoas pertencentes à mesma organização, **quando** o usuário registrar um vínculo direcional entre elas, **então** o sistema permite qualquer combinação entre PF e PJ.
2. **Dado** um vínculo do tipo “filho/filha” de uma Pessoa para outra, **quando** qualquer uma das duas Pessoas for consultada, **então** o sistema apresenta o mesmo vínculo com o rótulo apropriado para a direção consultada, sem criar outro relacionamento.
3. **Dado** um vínculo, **quando** o usuário informar seu tipo e uma descrição opcional, **então** a relação fica disponível para consulta nas duas Pessoas envolvidas.
4. **Dado** uma tentativa de relacionar uma Pessoa consigo mesma, **quando** o vínculo for salvo, **então** o sistema o recusa.
5. **Dado** uma Pessoa participante de relacionamentos, **quando** ela for excluída fisicamente, **então** seus relacionamentos são removidos sem bloquear a exclusão e sem alterar a outra Pessoa envolvida.

---

### História 5 — Gerir o ciclo de vida da Pessoa (Prioridade: P1)

Como usuário autorizado, quero inativar, reativar ou excluir uma Pessoa para manter a base organizacional correta sem perder registros ainda necessários.

**Por que esta prioridade**: cadastros deixam de ser utilizados, podem voltar a ser necessários e, quando não têm dependências, devem poder ser removidos.

**Teste independente**: inativar e reativar uma Pessoa; tentar excluí-la quando estiver em uso e quando estiver livre de uso.

**Cenários de aceite**:

1. **Dado** uma Pessoa ativa, **quando** for inativada, **então** deixa de ser apresentada nas seleções comuns, permanece consultável conforme a permissão aplicável e pode ser reativada.
2. **Dado** uma Pessoa inativa, **quando** for reativada, **então** volta a estar disponível para os usos permitidos.
3. **Dado** uma Pessoa que esteja sendo usada por outro módulo, **quando** o usuário solicitar sua exclusão física, **então** o sistema impede a exclusão e informa, sempre que identificável, quais usos precisam ser resolvidos.
4. **Dado** uma Pessoa sem uso que impeça sua remoção, **quando** o usuário solicitar a exclusão física, **então** o cadastro é removido.
5. **Dado** uma exclusão que não pôde ser antecipadamente classificada, **quando** houver impedimento de integridade, **então** o sistema preserva os dados e apresenta uma mensagem compreensível em vez de expor um erro técnico.

---

### História 6 — Reutilizar dados de um cadastro sem replicar identidade (Prioridade: P3)

Como usuário autorizado, quero duplicar uma Pessoa para acelerar cadastros semelhantes sem repetir seus documentos de identidade.

**Por que esta prioridade**: reduz trabalho operacional sem comprometer a identificação única da nova Pessoa.

**Teste independente**: duplicar uma Pessoa com endereço, contatos, conta e chave Pix; confirmar que a nova Pessoa exige sua própria identificação antes de ser concluída.

**Cenários de aceite**:

1. **Dado** uma Pessoa existente, **quando** o usuário iniciar a duplicação, **então** CPF, CNPJ e demais identificadores exclusivos não são reaproveitados pela nova Pessoa.
2. **Dado** dados relacionados passíveis de cópia, **quando** o usuário optar por copiá-los, **então** a nova Pessoa recebe registros independentes e alteráveis sem modificar a original.
3. **Dado** uma conta bancária ou chave Pix copiada, **quando** a nova Pessoa for salva, **então** a operação exige a confirmação ou correção de dados que não possam ser reutilizados com segurança.

### Casos de borda

- Pessoa sem CPF ou CNPJ é válida; CPF é aplicável apenas à PF e CNPJ apenas à PJ.
- Strings em branco não constituem documento, contato ou endereço válido.
- CPF e CNPJ, quando informados, devem ser validados e tratados de forma consistente para impedir duplicidade por formatação diferente.
- O nome de exibição não identifica exclusivamente uma Pessoa; Pessoas diferentes podem ter o mesmo nome de exibição.
- Um endereço brasileiro sem UF ou município vinculado não pode ser concluído.
- Um relacionamento não pode cruzar organizações.
- A exclusão física de uma Pessoa remove os relacionamentos dos quais ela participa, mas não exclui nem altera a outra Pessoa.
- A indisponibilidade ou inativação de uma referência de catálogo não pode apagar automaticamente um endereço ou conta já registrada.

## Requisitos

### Requisitos funcionais

- **FR-001**: O sistema DEVE manter Pessoas físicas e jurídicas separadas por organização, sem compartilhamento entre organizações.
- **FR-002**: Usuários autorizados DEVEM poder listar, buscar, consultar, cadastrar e alterar Pessoas da organização em contexto.
- **FR-003**: A busca DEVE localizar Pessoas por nome, nome de exibição, apelido/nome fantasia, CPF/CNPJ quando informados e valores de contato cadastrados.
- **FR-004**: Toda Pessoa DEVE ter tipo PF ou PJ, nome/razão social e nome de exibição calculado a partir do nome e, quando existir, do apelido/nome fantasia.
- **FR-005**: O sistema DEVE permitir Pessoa sem CPF ou CNPJ.
- **FR-006**: Para PF, o sistema DEVE aceitar somente CPF como documento tributário principal; para PJ, somente CNPJ.
- **FR-007**: CPF e CNPJ, quando informados, DEVEM ser válidos e exclusivos dentro da organização, inclusive entre cadastros inativos.
- **FR-008**: O sistema DEVE permitir registrar, quando aplicável, RG e órgão emissor, PIS/NIS, passaporte, documento estrangeiro, nascimento ou fundação e observações.
- **FR-009**: O sistema DEVE permitir múltiplos endereços por Pessoa, com rótulo, tipo, país, dados de localização, número textual, complemento, bairro e referência quando informados.
- **FR-010**: Todo endereço DEVE estar vinculado a um país; endereços brasileiros DEVEM estar vinculados também a UF e município.
- **FR-011**: O usuário DEVE poder informar rua ou logradouro textual sem que exista referência prévia no catálogo de localidades.
- **FR-012**: O sistema DEVE permitir múltiplos contatos tipados por Pessoa e validar seus valores conforme o tipo informado.
- **FR-013**: Esta fase NÃO DEVE exigir, calcular ou armazenar marcação de contato principal.
- **FR-014**: O sistema DEVE permitir múltiplas contas bancárias por Pessoa, com seus dados de identificação, tipo, situação e referência à instituição financeira quando disponível.
- **FR-015**: O sistema DEVE permitir múltiplas chaves Pix por Pessoa e validar cada chave conforme seu tipo.
- **FR-016**: Esta fase NÃO DEVE exigir, calcular ou armazenar marcação de chave Pix principal ou finalidade de chave Pix.
- **FR-017**: O sistema DEVE permitir relacionamentos entre duas Pessoas da mesma organização, independentemente de serem PF ou PJ.
- **FR-018**: Todo relacionamento DEVE possuir um tipo padronizado identificável e PODE possuir uma descrição livre sobre o vínculo.
- **FR-018A**: Um relacionamento DEVE ter uma direção única entre Pessoa de origem e Pessoa de destino; cada tipo padronizado, exceto `OUTROS`, DEVE possuir um tipo oposto definido para apresentação na consulta da outra Pessoa, sem criar vínculo recíproco adicional.
- **FR-018B**: O tipo `OUTROS` DEVE ser apresentado como seu próprio oposto, permitindo representar vínculos não previstos ou indiferentes para tratamento pelo sistema.
- **FR-019**: O sistema DEVE impedir relacionamento de uma Pessoa com ela própria.
- **FR-019A**: A exclusão física de uma Pessoa DEVE remover os relacionamentos dos quais ela participa, sem excluir ou alterar as outras Pessoas relacionadas.
- **FR-020**: O sistema DEVE permitir inativar e reativar Pessoas, preservando seus dados para consulta autorizada e evitando seu uso nas seleções comuns enquanto inativas.
- **FR-021**: O sistema DEVE permitir exclusão física de Pessoa quando não houver uso que a impeça.
- **FR-022**: Antes de excluir uma Pessoa, o sistema DEVE identificar e comunicar os usos conhecidos que impedem a exclusão; caso surja um impedimento não antecipado, DEVE apresentar erro compreensível e preservar os dados.
- **FR-023**: O sistema DEVE registrar as operações de criação, alteração, inativação, reativação e exclusão de Pessoa para auditoria de manutenção organizacional.
- **FR-024**: O sistema DEVE oferecer criação rápida de Pessoa com os dados mínimos definidos para PF ou PJ, mantendo as mesmas regras de validação e isolamento da criação completa.
- **FR-025**: O sistema DEVE permitir duplicação de Pessoa com escolha explícita sobre quais dados relacionados podem ser copiados, sem reutilizar CPF, CNPJ ou outros identificadores exclusivos.
- **FR-026**: O sistema DEVE apresentar erros de validação de dados relacionados no item e campo que requerem correção.
- **FR-027**: Permissões específicas para as ações desta feature DEVEM ser conciliadas com o modelo definitivo de permissões da plataforma antes da implementação; esta especificação não define papéis nem grupos.

### Entidades principais

- **Pessoa**: cadastro organizacional de uma pessoa física ou jurídica, sua identificação, nome, situação e dados complementares autorizados nesta fase.
- **Endereço da Pessoa**: endereço final pertencente à Pessoa, que pode apontar para referências de localização existentes, sem depender delas para registrar uma rua textual.
- **Contato da Pessoa**: canal de comunicação tipado associado à Pessoa, sem hierarquia de principal nesta fase.
- **Conta Bancária da Pessoa**: dados bancários associados à Pessoa e, quando disponível, à instituição financeira do catálogo corporativo.
- **Chave Pix da Pessoa**: chave de recebimento ou identificação Pix, com tipo e valor validados.
- **Relacionamento entre Pessoas**: vínculo direcional entre duas Pessoas da mesma organização, com classificação padronizada, tipo oposto de apresentação e descrição opcional.

## Critérios de sucesso

### Resultados mensuráveis

- **CS-001**: 100% dos cenários de aceite P1 são aprovados antes de disponibilizar a feature para uso organizacional.
- **CS-002**: Em teste de aceitação, um usuário autorizado conclui o cadastro básico de uma PF ou PJ sem documento em até 2 minutos, sem assistência técnica.
- **CS-003**: 100% das tentativas de consulta ou alteração de Pessoa fora da organização em contexto são negadas.
- **CS-004**: 100% das exclusões bloqueadas por uso conhecido apresentam ao usuário uma explicação acionável sobre o impedimento.
- **CS-005**: Em condições normais de operação, ao menos 95% das buscas por Pessoa retornam sua lista inicial em até 2 segundos.
