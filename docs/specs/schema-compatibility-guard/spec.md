# Especificação da Feature: Compatibilidade de Schemas e Guarda Operacional

**Feature**: `schema-compatibility-guard`
**Criada em**: 2026-09-30
**Status**: Pronta para planejamento

## Contexto e limite de escopo

A plataforma depende de um schema global e de schemas isolados por organização. Uma versão da aplicação só é segura quando os catálogos de dados requeridos por ela foram integralmente aplicados. Hoje, uma aplicação pode iniciar enquanto o schema global ou um schema de organização permanece atrasado, permitindo que a interface ofereça uma operação cujo armazenamento ainda não está compatível.

Esta feature impede o atendimento funcional com schemas incompatíveis. Ela distingue a indisponibilidade global, que afeta toda a plataforma, da indisponibilidade isolada de uma organização, que não deve interromper recursos pessoais, outras organizações compatíveis ou a operação administrativa necessária para recuperar a situação.

> [!IMPORTANT]
> A atualização do schema global continua sendo uma etapa explícita e obrigatória de implantação. Esta feature não transforma a inicialização da aplicação em executor de alterações de banco.

Ficam fora do escopo: alteração automática de dados de negócio, reversão automática de migrations, edição manual de histórico de migrations, uma tela genérica de manutenção de dados e execução de deploy pela interface.

## Cobertura de interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Entrada da plataforma | Web responsiva | Pessoas visitantes e autenticadas | FULL | Comunica indisponibilidade global controlada e não oferece operações de negócio enquanto o schema global estiver incompatível. | Diagnóstico técnico, nomes de schema, versões internas ou instruções de infraestrutura. |
| Consumidores da plataforma | API JSON autenticada e não autenticada | Interface e consumidores autorizados | FULL | Recusa operações funcionais durante indisponibilidade global e responde de forma estável quando a organização solicitada estiver em atualização ou incompatível. | Endpoints administrativos internos necessários à recuperação operacional. |
| Workspace organizacional | Web responsiva | Usuários autorizados de organizações | FULL | Mantém o uso pessoal e de organizações compatíveis, mas impede seleção e operações da organização incompatível, explicando que ela está temporariamente sendo atualizada. | Gestão de migrations ou detalhes técnicos pelo usuário final. |
| Operação de atualização | Processo operacional supervisionado | Equipe de operação e mecanismos de implantação | FULL | Identifica organizações incompatíveis, atualiza cada uma de forma controlada e só restabelece o uso após validação completa. | Execução automática durante a inicialização de cada instância web. |

## Cenários de usuário e testes

### História 1 — Bloquear a plataforma diante de incompatibilidade global (Prioridade: P1)

Como pessoa que acessa a plataforma, quero receber uma indisponibilidade clara quando a base global não estiver compatível, para não iniciar nem concluir uma operação que possa falhar ou produzir dados inconsistentes.

**Por que esta prioridade**: o schema global é pré-requisito para identidade, autorização, contexto e catálogos compartilhados; sua incompatibilidade torna inseguro atender qualquer função de negócio.

**Teste independente**: disponibilizar uma versão da aplicação cujo catálogo global seja mais novo que o histórico aplicado e confirmar que páginas e operações funcionais ficam indisponíveis, sem revelar detalhes internos.

**Cenários de aceite**:

1. **Dado** que uma atualização global obrigatória ainda não foi aplicada, **quando** uma pessoa tentar usar a interface, **então** a plataforma mostra uma mensagem segura de indisponibilidade por atualização e não oferece funções de negócio.
2. **Dado** que uma atualização global obrigatória ainda não foi aplicada, **quando** um consumidor tentar uma operação da API, **então** recebe uma resposta estável de indisponibilidade, sem versões, nomes de schema, SQL ou credenciais.
3. **Dado** que o schema global volta a ficar compatível, **quando** uma nova solicitação for recebida, **então** a plataforma volta a atender normalmente sem reinicialização manual exigida do usuário.

---

### História 2 — Isolar uma organização em atualização (Prioridade: P1)

Como usuário que participa de mais de uma organização, quero continuar usando meu espaço pessoal e organizações compatíveis enquanto uma organização específica é atualizada, para que uma manutenção localizada não interrompa o restante do meu trabalho.

**Por que esta prioridade**: o isolamento por organização deve preservar disponibilidade e evitar que a aplicação execute uma funcionalidade contra um schema atrasado.

**Teste independente**: manter duas organizações para a mesma pessoa, tornar apenas uma incompatível e confirmar que ela não pode ser selecionada ou operada, enquanto a outra continua utilizável.

**Cenários de aceite**:

1. **Dado** uma organização com schema atrasado, **quando** a pessoa abrir ou tentar iniciar seu contexto, **então** ela é informada de que a organização está temporariamente em atualização e não recebe módulos organizacionais.
2. **Dado** uma organização já selecionada que se torna indisponível para atualização, **quando** uma nova operação contextual for solicitada, **então** a operação é recusada de forma segura e o estado contextual incompatível deixa de ser utilizável.
3. **Dado** outra organização compatível e recursos pessoais autorizados, **quando** uma organização diferente estiver em atualização, **então** eles permanecem disponíveis.
4. **Dado** que a atualização de uma organização falhou, **quando** alguém tentar usá-la, **então** ela permanece indisponível até uma recuperação bem-sucedida, sem ser tratada como ativa por engano.

---

### História 3 — Atualizar organizações existentes com segurança (Prioridade: P1)

Como responsável operacional, quero que organizações existentes sejam identificadas e atualizadas de forma controlada após uma nova versão ser disponibilizada, para que cada organização volte a operar somente quando seu catálogo estiver completo.

**Por que esta prioridade**: o provisionamento de uma organização nova não cobre organizações já existentes; sem esse fluxo, a compatibilidade depende de intervenção manual insegura e incompleta.

**Teste independente**: introduzir uma atualização aplicável a organizações existentes, processar uma organização compatível e outra com falha, e confirmar que somente a primeira retorna ao uso.

**Cenários de aceite**:

1. **Dado** uma organização existente com catálogo atrasado, **quando** a atualização operacional for iniciada, **então** ela é colocada em indisponibilidade controlada antes de qualquer operação contextual ser atendida.
2. **Dado** uma atualização concluída para uma organização, **quando** o catálogo aplicado for conferido, **então** ela volta a ficar disponível somente se estiver completo.
3. **Dado** uma falha transitória durante a atualização, **quando** a política de repetição permitir nova tentativa, **então** a organização continua indisponível e a tentativa é retomada de forma segura.
4. **Dado** uma falha terminal ou tentativas esgotadas, **quando** a atualização não puder ser concluída, **então** a organização permanece indisponível e o fato fica disponível para a operação responsável sem expor detalhes técnicos a usuários comuns.

---

### História 4 — Preparar uma organização nova já compatível (Prioridade: P2)

Como criador de uma organização, quero que ela só seja disponibilizada depois de receber todo o catálogo necessário, para não encontrar uma organização ativa sem estrutura para seus módulos.

**Por que esta prioridade**: mantém o contrato já existente de provisionamento e o alinha à mesma definição de compatibilidade usada nas atualizações posteriores.

**Teste independente**: criar uma organização após a disponibilização de uma nova versão e confirmar que ela se torna selecionável somente depois da confirmação completa de seu catálogo.

**Cenários de aceite**:

1. **Dado** uma organização recém-criada, **quando** sua preparação estiver em andamento, **então** ela não é selecionável nem atende operações de organização.
2. **Dado** que a preparação concluiu com o catálogo completo, **quando** a organização for liberada, **então** ela pode ser selecionada e atender suas operações autorizadas.
3. **Dado** uma falha de preparação, **quando** a pessoa consultar a organização, **então** ela encontra uma indicação segura de indisponibilidade, sem acesso parcial ao schema.

---

### História 5 — Recuperar a operação após atualização (Prioridade: P2)

Como responsável operacional, quero distinguir uma indisponibilidade por atualização de uma falha de infraestrutura comum, para aplicar a recuperação correta e confirmar o retorno seguro da plataforma ou de uma organização.

**Por que esta prioridade**: mensagens e estados claros reduzem tentativas indevidas de uso e aceleram a recuperação sem expor informações internas a usuários finais.

**Teste independente**: simular incompatibilidade global, atualização de organização e falha de atualização, verificando que cada condição possui comunicação segura e evidência operacional correspondente.

**Cenários de aceite**:

1. **Dado** que a plataforma está bloqueada por incompatibilidade global, **quando** a equipe responsável consultar a evidência operacional autorizada, **então** consegue identificar que a atualização global é necessária.
2. **Dado** uma organização em atualização ou com falha, **quando** a equipe responsável consultar seu acompanhamento operacional autorizado, **então** consegue identificar seu estado, tentativa mais recente e resultado sem depender de logs da interface de usuário.
3. **Dado** que a compatibilidade foi restabelecida, **quando** a equipe confirmar o estado, **então** a plataforma ou organização correspondente deixa de constar como indisponível por atualização.

### Casos de borda

- Duas instâncias da aplicação iniciadas ao mesmo tempo devem chegar à mesma decisão de bloqueio global, sem uma delas atender funções enquanto a outra reconhece incompatibilidade.
- O estado de compatibilidade não pode ser reutilizado indefinidamente: uma alteração no histórico de migrations deve ser percebida em novas solicitações sem exigir que usuários limpem cache do navegador.
- Uma organização desabilitada por decisão administrativa não pode ser confundida com uma organização em atualização; ambas continuam indisponíveis, mas possuem causas operacionais distintas.
- A indisponibilidade de uma organização não pode ocultar ou invalidar associações, permissões, arquivos ou dados de outra organização.
- Uma atualização já em andamento não pode ser duplicada nem executar concorrentemente para a mesma organização.
- Se o histórico de migrations estiver ausente, ilegível ou incompleto, a condição deve ser tratada como incompatível até validação bem-sucedida.
- Erros de conexão ao banco durante a verificação devem resultar em indisponibilidade controlada, e nunca em uma suposição de compatibilidade.

## Requisitos

### Requisitos funcionais

- **FR-SCG-001**: O sistema DEVE determinar, antes de atender uma função de negócio, se o catálogo global exigido pela versão em execução está integralmente aplicado.
- **FR-SCG-002**: Quando o catálogo global não estiver compatível ou não puder ser validado, o sistema DEVE bloquear a interface e as operações funcionais da API com comunicação segura e consistente de indisponibilidade temporária.
- **FR-SCG-003**: O bloqueio global NÃO DEVE expor detalhes técnicos, histórico de migrations, versões internas, nomes de schema, SQL, credenciais ou caminhos de recuperação a pessoas usuárias ou consumidores comuns.
- **FR-SCG-004**: O bloqueio global NÃO DEVE depender de uma nova inicialização da aplicação para ser removido depois que a compatibilidade for restabelecida.
- **FR-SCG-005**: O sistema DEVE validar a compatibilidade de uma organização antes de iniciar seu contexto ou executar uma operação que use seus dados.
- **FR-SCG-006**: Quando somente uma organização estiver incompatível, o sistema DEVE bloquear exclusivamente seu contexto e suas operações, preservando recursos pessoais e organizações compatíveis aos quais a pessoa tenha acesso.
- **FR-SCG-007**: O sistema DEVE comunicar a indisponibilidade de uma organização como atualização temporária ou falha de atualização, sem revelar detalhes internos de dados ou infraestrutura.
- **FR-SCG-008**: O sistema DEVE identificar organizações existentes cujo catálogo esteja atrasado e submetê-las a um processo de atualização controlado antes de restabelecer seu uso.
- **FR-SCG-009**: Durante uma atualização de organização, o sistema DEVE recusar novas operações contextuais e impedir execuções simultâneas da mesma atualização.
- **FR-SCG-010**: O sistema DEVE liberar uma organização para uso somente depois de confirmar que todas as atualizações exigidas foram aplicadas com sucesso.
- **FR-SCG-011**: O sistema DEVE manter uma organização indisponível quando a atualização falhar, aplicando a política configurada de nova tentativa somente quando a falha for transitória.
- **FR-SCG-012**: O sistema DEVE preservar a preparação inicial de organizações novas como processo automático e exigir a mesma confirmação completa de compatibilidade antes de ativá-las.
- **FR-SCG-013**: O sistema DEVE produzir evidência operacional suficiente para distinguir incompatibilidade global, organização em atualização, atualização concluída e atualização com falha, respeitando a política geral de retenção configurada pelo ambiente.
- **FR-SCG-014**: A atualização do catálogo global DEVE permanecer uma etapa explícita de implantação, concluída antes de liberar a versão da aplicação; o sistema NÃO DEVE executar migrations globais automaticamente ao iniciar uma instância web.
- **FR-SCG-015**: A API e a interface DEVEM usar uma identificação estável para a indisponibilidade por incompatibilidade, permitindo tratar a condição sem interpretar mensagens de texto.
- **FR-SCG-INFRA-LOCK**: O sistema DEVE serializar a atualização de cada organização entre processos concorrentes e recusar uma segunda execução enquanto houver uma atualização válida em andamento.
- **FR-SCG-INFRA-SCHED**: A identificação e o disparo de atualizações de organizações existentes DEVEM ocorrer por processo operacional supervisionado, com execução acionada pela disponibilização de uma versão e retomada segura de trabalhos pendentes; não devem depender da inicialização de instâncias web nem de ação de usuário final.

### Entidades principais

- **Compatibilidade global**: condição observável que informa se o catálogo compartilhado exigido pela versão da aplicação está completo e acessível.
- **Compatibilidade da organização**: condição observável que informa se o catálogo isolado de uma organização está completo e acessível para a versão da aplicação.
- **Atualização de organização**: ciclo operacional idempotente que identifica incompatibilidade, impede uso parcial, aplica atualizações, registra seu resultado e libera a organização somente após validação.
- **Evidência operacional de compatibilidade**: registro seguro de estados, tentativas e resultados usados pela equipe responsável para acompanhar recuperação, sem se tornar log técnico público.

## Critérios de sucesso

### Resultados mensuráveis

- **SC-SCG-001**: 100% dos cenários automatizados com catálogo global incompleto bloqueiam páginas e operações funcionais antes de qualquer regra de negócio ou gravação de dados ser executada.
- **SC-SCG-002**: 100% dos cenários automatizados com uma organização incompatível bloqueiam somente suas operações, preservando o acesso autorizado a recursos pessoais e outras organizações compatíveis.
- **SC-SCG-003**: 100% dos cenários automatizados de atualização concluída liberam a organização somente após a confirmação de catálogo completo; nenhum cenário libera uma organização com atualização pendente ou falha.
- **SC-SCG-004**: 100% dos cenários automatizados de concorrência demonstram no máximo uma atualização ativa por organização.
- **SC-SCG-005**: Em testes de indisponibilidade, nenhuma resposta voltada a usuários ou consumidores comuns contém detalhes de schema, SQL, credenciais, histórico ou versão interna de migrations.
- **SC-SCG-006**: Após a conclusão comprovada de uma atualização global ou de organização, novas solicitações compatíveis voltam a ser atendidas sem ação adicional de usuários finais.
