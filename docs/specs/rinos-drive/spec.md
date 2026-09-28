# Especificação da Feature: Rinos Drive

**Feature**: `rinos-drive`  
**Criada em**: 2026-09-27  
**Status**: Draft  
**Referências**: [Fundação de armazenamento de arquivos](../file-storage-foundation/spec.md), [Fundação de autorização](../authorization-foundation/spec.md) e [Shell de workspace](../workspace-shell/spec.md)

## Direção de Produto

O Rinos Drive torna navegável o conteúdo privado já mantido pela fundação de arquivos. Ele é um único módulo com duas apresentações: **Rinos Drive Pessoal**, para o workspace do usuário autenticado, e **Rinos Drive Work**, para o workspace da organização selecionada na aba.

O produto não cria outro sistema de armazenamento, nem confere acesso por mera associação a uma organização. Ele permite organizar, enviar, localizar e recuperar arquivos dentro dos limites de cada workspace e das permissões já concedidas sobre suas pastas.

## Clarificações

### Sessão 2026-09-28

- **Pergunta**: como operações simultâneas que usam o mesmo nome em uma localização evitam duplicidade ou sobrescrita?
- **Resposta**: o servidor resolve e reserva o nome final como parte da mesma operação atômica no workspace e na localização de destino. Cada operação concorrente confirma um nome distinto antes de ativar o novo item.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Rinos Drive Pessoal | Web responsiva | Usuário autenticado e colaboradores já autorizados | FULL | Navegar, organizar, enviar, baixar, recuperar e consultar arquivos do workspace pessoal e de pastas recebidas. | Compartilhar arquivos, editar conteúdo, prévias ricas, álbuns e links públicos. |
| Rinos Drive Work | Web responsiva | Administrador e membro autorizado da organização ativa | FULL | Navegar e operar somente pastas organizacionais autorizadas, conforme a permissão efetiva. | Convites, gestão visual de permissões, links públicos, prévias ricas e edição de conteúdo. |
| API de workspace | Other | Interfaces autenticadas e futuras integrações autorizadas | FULL | Expor operações privadas de navegação, pastas, arquivos, lixeira e exportações, sem revelar caminhos físicos. | URL pública de arquivo, compartilhamento externo e gerenciamento administrativo de volumes. |
| Visualização de mídia | Web responsiva | Usuário autenticado | DEFERRED | Reservar espaço para identificadores de tipo e futuras representações. | Gerar ou entregar thumbnails, prévias de PDF, vídeo, áudio, imagem ou documento. |

## Cenários de Usuário e Testes

### User Story 1 — Navegar e organizar o Rinos Drive Pessoal (Prioridade: P1)

Como usuário autenticado, quero navegar e organizar meus arquivos em uma árvore de pastas para localizar e manter meu workspace pessoal sem depender do sistema de arquivos do dispositivo.

**Por que esta prioridade**: entrega o núcleo útil do módulo e transforma a fundação privada existente em uma capacidade utilizável.

**Teste independente**: criar pastas e arquivos no workspace pessoal, percorrer a árvore, mudar a apresentação e confirmar que cada ação reflete apenas o conteúdo autorizado.

**Cenários de aceitação**:

1. **Dado** um usuário autenticado, **quando** abre Rinos Drive Pessoal, **então** vê a raiz do seu workspace, a árvore de pastas e a área de conteúdo sem depender de uma organização selecionada.
2. **Dado** uma pasta ativa, **quando** o usuário cria, renomeia ou move uma pasta para um destino válido, **então** a árvore e o conteúdo refletem a nova organização sem criar ciclos ou nomes duplicados entre irmãos.
3. **Dado** uma pasta ou arquivo selecionado, **quando** o usuário alterna entre grade, lista, detalhes ou tabela, **então** vê os mesmos itens autorizados em outra apresentação, preservando a seleção quando ela ainda é válida.
4. **Dado** que o usuário recebeu leitura de uma pasta pessoal de outra pessoa por um mecanismo autorizado, **quando** abre o Drive Pessoal, **então** encontra somente essa árvore compartilhada e seus descendentes permitidos, sem receber visibilidade sobre itens vizinhos.

---

### User Story 2 — Enviar e baixar arquivos com segurança (Prioridade: P1)

Como usuário com edição em uma pasta, quero enviar vários arquivos para a pasta atual e baixar um ou mais itens autorizados para trabalhar com eles fora da plataforma.

**Por que esta prioridade**: upload e download completam o ciclo mínimo de um drive, mantendo os arquivos no catálogo privado já aprovado.

**Teste independente**: selecionar múltiplos arquivos locais, enviá-los a uma pasta editável, conferir a listagem e baixar um item ou uma seleção múltipla autorizada.

**Cenários de aceitação**:

1. **Dado** que o usuário possui edição na pasta atual, **quando** seleciona múltiplos arquivos válidos, **então** todos são enviados para essa pasta e recebem feedback individual de progresso, sucesso ou falha.
2. **Dado** que um envio possui nome já usado na pasta, **quando** o conteúdo é aceito, **então** o Drive preserva ambos os itens e atribui ao novo um nome não ambíguo, como `Relatório (2).pdf`.
3. **Dado** um único arquivo legível, **quando** o usuário solicita download, **então** recebe somente aquele conteúdo privado, sem caminho interno ou URL pública.
4. **Dado** dois ou mais itens legíveis, **quando** o usuário solicita download, **então** recebe uma exportação compactada que preserva a estrutura selecionada e não expõe itens não selecionados.
5. **Dado** que o usuário não possui edição no destino, **quando** tenta enviar, mover, criar ou alterar conteúdo, **então** a operação é recusada sem revelar detalhes de outros itens ou permissões.

---

### User Story 3 — Trabalhar em um Rinos Drive Work delimitado (Prioridade: P1)

Como membro de uma organização, quero acessar somente as pastas de trabalho para as quais recebi acesso, enquanto administradores mantêm acesso completo ao workspace organizacional.

**Por que esta prioridade**: impede que a organização seja tratada como um espaço público para todos os membros e protege documentos de equipes, áreas e projetos distintos.

**Teste independente**: conceder leitura ou edição em uma pasta organizacional a um membro, comparar sua visão com a de um administrador e revogar o acesso durante a sessão.

**Cenários de aceitação**:

1. **Dado** um administrador ativo da organização selecionada, **quando** abre Rinos Drive Work, **então** pode navegar e operar todo o workspace organizacional dentro das ações previstas.
2. **Dado** um membro ativo com leitura em uma pasta organizacional, **quando** abre Rinos Drive Work, **então** vê apenas essa pasta e seus descendentes legíveis, podendo navegar e baixar, mas sem ações de alteração.
3. **Dado** um membro ativo com edição em uma pasta organizacional, **quando** abre essa pasta ou um descendente, **então** pode organizar pastas e arquivos dentro desse ramo, sem obter acesso a ramos vizinhos.
4. **Dado** um membro ativo sem relação de acesso aplicável, **quando** abre Rinos Drive Work, **então** recebe um estado vazio neutro e não descobre nomes, quantidade ou estrutura do acervo organizacional.
5. **Dado** que uma relação de acesso é revogada, **quando** o usuário atualiza, navega ou executa uma nova ação, **então** a pasta deixa de ser acessível e a interface remove o item sem expor seu conteúdo anterior.

---

### User Story 4 — Recuperar organização acidentalmente removida (Prioridade: P2)

Como usuário autorizado, quero usar a lixeira do workspace para recuperar arquivos ou pastas removidos por engano antes do prazo de retenção.

**Por que esta prioridade**: a movimentação e a organização em lote precisam de proteção contra perda acidental.

**Teste independente**: enviar arquivo e pasta à lixeira, restaurá-los dentro do prazo, limpar um item definitivamente e confirmar a atualização do consumo exibido.

**Cenários de aceitação**:

1. **Dado** um arquivo ou pasta que o usuário pode editar, **quando** o envia à lixeira, **então** ele deixa a navegação ativa e pode ser restaurado enquanto estiver dentro do prazo configurado.
2. **Dado** uma pasta enviada à lixeira, **quando** o usuário a restaura dentro do prazo, **então** a árvore e os arquivos pertencentes retornam como conjunto consistente.
3. **Dado** um item na lixeira, **quando** o usuário confirma a limpeza definitiva, **então** perde o vínculo recuperável daquele workspace sem afetar cópias ou vínculos válidos de outros workspaces.
4. **Dado** uma seleção mista com itens sem edição, **quando** o usuário tenta removê-la, **então** somente uma ação integralmente autorizada é aceita; o sistema não remove parcialmente em silêncio.

---

### User Story 5 — Alternar visualizações e consultar informações (Prioridade: P2)

Como usuário, quero escolher a forma de visualizar o conteúdo e abrir detalhes do item sem interromper a navegação.

**Por que esta prioridade**: workspaces com documentos heterogêneos exigem leitura rápida em contextos e tamanhos de tela diferentes.

**Teste independente**: abrir uma pasta com arquivos de tipos distintos, alternar cada visualização e consultar o painel de detalhes por teclado e por toque.

**Cenários de aceitação**:

1. **Dado** conteúdo acessível, **quando** o usuário seleciona grade, lista, detalhes ou tabela, **então** a visualização informa nome, tipo, tamanho, datas e localização lógica conforme disponível.
2. **Dado** um item selecionado, **quando** o usuário abre o painel de detalhes, **então** consulta somente metadados permitidos sem iniciar download nem prévia do conteúdo.
3. **Dado** uma tela estreita, **quando** o usuário navega, abre árvore ou painel de detalhes, **então** o conteúdo principal permanece utilizável e os painéis auxiliares não eliminam o acesso às ações essenciais.

---

### User Story 6 — Receber uma exportação temporária controlada (Prioridade: P2)

Como usuário autorizado, quero receber um arquivo compactado ao baixar múltiplos itens sem transformar essa exportação em um arquivo permanente do meu workspace.

**Por que esta prioridade**: exportações são úteis, mas não devem gerar lixo, consumo de quota ou itens navegáveis sem intenção do usuário.

**Teste independente**: solicitar exportação de múltiplos itens, acompanhar o estado, baixar o resultado dentro do prazo e confirmar sua indisponibilidade após expiração.

**Cenários de aceitação**:

1. **Dado** uma seleção múltipla autorizada, **quando** a exportação é solicitada, **então** o usuário vê estado de preparação e recebe o download ao concluir.
2. **Dado** uma exportação pronta, **quando** o usuário a baixa durante seu período de disponibilidade, **então** o sistema revalida sua autorização antes de entregar o conteúdo.
3. **Dado** uma exportação expirada, cancelada ou cujo acesso foi revogado, **quando** o usuário tenta baixá-la, **então** o sistema não entrega bytes nem expõe a localização temporária.
4. **Dado** que o espaço ou os limites temporários da instância foram atingidos, **quando** uma nova exportação é solicitada, **então** o usuário recebe uma mensagem clara e nenhum arquivo parcial fica disponível.

### Casos de Borda

- Uma alteração de contexto organizacional fecha o Rinos Drive Work daquela organização e nunca carrega seu estado em outro tenant.
- A raiz do workspace é implícita; usuários com acesso somente a uma pasta compartilhada não podem inferir ou navegar pela raiz, por ancestrais ou por irmãos não autorizados.
- Renomeações, uploads e movimentações concorrentes reservam nomes finais distintos entre irmãos antes de ativar qualquer item; conflitos recebem nome automático seguro, sem sobrescrever conteúdo existente.
- Nomes de arquivo ou pasta inválidos, reservados, excessivamente longos ou perigosos são recusados de modo compreensível e não aparecem em exportações, cabeçalhos ou caminhos inseguros.
- Arquivos, pastas ou permissões alterados enquanto a tela está aberta são revalidados antes de cada operação relevante; dados previamente carregados não autorizam uma nova ação.
- Itens `SYSTEM_MANAGED`, como avatar, não aparecem no Drive, não podem ser manipulados pelas operações de workspace e continuam sujeitos ao seu próprio ciclo de vida.
- Falha de upload, conexão perdida, interrupção de exportação ou indisponibilidade temporária não cria item parcialmente ativo, download parcial reutilizável ou alteração silenciosa da árvore.
- Um arquivo compactado para exportação preserva a hierarquia selecionada, elimina trajetórias perigosas e resolve colisões de nome dentro do pacote sem substituir outro item.

## Requisitos

### Requisitos Funcionais

- **FR-DRIVE-001**: O sistema DEVE apresentar o módulo com o nome Rinos Drive e distinguir Rinos Drive Pessoal de Rinos Drive Work conforme o workspace aberto.
- **FR-DRIVE-002**: O Rinos Drive Pessoal DEVE abrir o workspace do usuário autenticado sem exigir contexto organizacional.
- **FR-DRIVE-003**: O Rinos Drive Work DEVE abrir somente para a organização ativa na aba e DEVE ser encerrado ou invalidado quando esse contexto deixar de ser válido.
- **FR-DRIVE-004**: O cliente NÃO DEVE escolher livremente um proprietário ou workspace; cada operação DEVE ser resolvida contra a identidade autenticada e o contexto organizacional autorizado.
- **FR-DRIVE-005**: O sistema DEVE exibir árvore hierárquica, localização atual, conteúdo da localização e lixeira para cada workspace acessível, sem expor árvores ou itens fora da decisão de acesso efetiva.
- **FR-DRIVE-006**: O sistema DEVE permitir criar, renomear e mover pastas somente quando a ação for autorizada e preservar as invariantes de hierarquia, escopo e nome entre irmãos.
- **FR-DRIVE-007**: O sistema DEVE permitir upload múltiplo para a pasta atual quando houver edição, mantendo progresso e resultado individual de cada arquivo aceito ou recusado.
- **FR-DRIVE-008**: O sistema DEVE validar cada arquivo enviado segundo limites de tamanho, quantidade, tipo e total de lote configuráveis pela instância, sem confiar exclusivamente nas informações declaradas pelo navegador.
- **FR-DRIVE-009**: Diante de nome ocupado, inclusive por operação concorrente na mesma localização, o sistema DEVE preservar ambos os itens e atribuir ao novo item um nome seguro e distinguível, reservado atomicamente antes de sua ativação, sem substituição implícita nem criação de versão por acidente.
- **FR-DRIVE-010**: O sistema DEVE permitir download privado de um item legível e DEVE compactar automaticamente uma seleção múltipla em exportação temporária privada.
- **FR-DRIVE-011**: O sistema DEVE preservar a hierarquia selecionada e resolver colisões de nomes na exportação múltipla, sem incluir item não autorizado.
- **FR-DRIVE-012**: O sistema DEVE manter exportações temporárias fora da árvore do workspace, fora da quota do usuário e indisponíveis após expiração, cancelamento ou perda de autorização.
- **FR-DRIVE-013**: O sistema DEVE usar prazo padrão de 60 minutos para exportação temporária e DEVE permitir configurá-lo por ambiente, junto de limites de quantidade, tamanho, espaço temporário e limpeza automática.
- **FR-DRIVE-014-INFRA-SCHED**: O sistema DEVE remover exportações temporárias expiradas automaticamente em rotina configurável; essa limpeza deve operar de modo seguro perante requisições repetidas ou execução concorrente.
- **FR-DRIVE-015**: O sistema DEVE mover arquivos e pastas removidos para a lixeira e permitir restauro ou limpeza definitiva somente enquanto as regras de retenção aplicáveis permitirem.
- **FR-DRIVE-016**: O sistema DEVE disponibilizar grade, lista, detalhes e tabela para o mesmo conteúdo autorizado, além de painel lateral de informações sem iniciar prévia ou download não solicitado.
- **FR-DRIVE-017**: O Rinos Drive DEVE ser utilizável por teclado, toque e leitor de tela, oferecendo foco, atalhos que não interfiram em campos de texto e equivalentes acessíveis para todas as ações visíveis.
- **FR-DRIVE-018**: A permissão `personal.folder.read` DEVE permitir visualizar, navegar, consultar metadados seguros e baixar conteúdo no ramo pessoal concedido, incluindo descendentes.
- **FR-DRIVE-019**: A permissão `personal.folder.edit` DEVE incluir as capacidades de leitura e permitir organizar pastas e arquivos, enviar conteúdo, mover, enviar à lixeira, restaurar e limpar itens no ramo pessoal concedido, incluindo descendentes.
- **FR-DRIVE-020**: A permissão `tenant.folder.read` DEVE permitir visualizar, navegar, consultar metadados seguros e baixar conteúdo somente no ramo organizacional concedido, incluindo descendentes.
- **FR-DRIVE-021**: A permissão `tenant.folder.edit` DEVE incluir as capacidades de leitura e permitir organizar pastas e arquivos, enviar conteúdo, mover, enviar à lixeira, restaurar e limpar itens somente no ramo organizacional concedido, incluindo descendentes.
- **FR-DRIVE-022**: Administradores ativos de uma organização DEVEM possuir acesso efetivo de leitura e edição a todo o Rinos Drive Work dessa organização, sem exigir relações individuais por pasta.
- **FR-DRIVE-023**: Membros organizacionais não administrativos NÃO DEVEM obter visibilidade, leitura ou edição por mera associação ao tenant; devem possuir relação direta ou por grupo aplicável à pasta solicitada.
- **FR-DRIVE-024**: Restrições de acesso válidas DEVEM prevalecer sobre permissões, relações ou condição administrativa aplicáveis, conforme a política de autorização da plataforma.
- **FR-DRIVE-025**: A criação, alteração, convite, remoção ou auditoria visual de relações de acesso NÃO faz parte desta feature; o Drive somente aplica permissões concedidas por capacidades autorizadas existentes ou futuras.
- **FR-DRIVE-026**: O sistema NÃO DEVE bloquear upload pelo consumo de quota nesta entrega, mas DEVE apresentar os consumos disponíveis sem incluir exportações temporárias.
- **FR-DRIVE-027**: O sistema NÃO DEVE exibir, alterar ou baixar ativos gerenciados pelo sistema, caminhos físicos, hashes, chaves de armazenamento, URLs públicas ou informação equivalente.
- **FR-DRIVE-028**: A primeira entrega NÃO DEVE oferecer links externos, compartilhamento público, edição de conteúdo, prévias ou thumbnails, álbuns, classificação automática, busca global ou gestão de backends.

### Entidades Principais

- **Alvo de workspace**: contexto pessoal do usuário autenticado ou contexto organizacional ativo e autorizado; identifica a origem funcional de toda navegação sem se tornar um seletor livre do cliente.
- **Localização de Drive**: raiz implícita, pasta ativa ou lixeira de um workspace, com breadcrumb e conteúdo autorizado.
- **Item de Drive**: projeção segura de pasta ou arquivo exibida na árvore, na área de conteúdo e nas visualizações, sem dados físicos internos.
- **Seleção de Drive**: conjunto de itens de uma localização sobre o qual ações em lote só ocorrem se a autorização permitir o conjunto completo.
- **Exportação temporária**: resultado privado e efêmero de uma seleção múltipla, com solicitante, workspace, estado, prazo e limites próprios; não é arquivo do workspace.
- **Relação de pasta**: concessão de leitura ou edição, direta ou por grupo, que se aplica ao ramo da pasta e aos seus descendentes conforme a autorização efetiva.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-DRIVE-001**: Em testes automatizados, 100% das tentativas de navegação, download, upload, movimentação e exclusão fora do workspace ou ramo autorizado são negadas sem exposição de conteúdo ou caminho físico.
- **SC-DRIVE-002**: Em testes automatizados, 100% dos administradores ativos conseguem acessar o workspace completo de sua organização, e 100% dos membros sem relação aplicável recebem visão vazia sem itens organizacionais.
- **SC-DRIVE-003**: Em testes automatizados, 100% dos conflitos de nome, inclusive concorrentes, preservam o item existente e produzem um novo nome distinguível e único para o item recebido.
- **SC-DRIVE-004**: Em testes automatizados, 100% das exportações múltiplas contêm somente itens autorizados, preservam a hierarquia selecionada e tornam-se indisponíveis após o prazo configurado.
- **SC-DRIVE-005**: Em validações de interface, usuários completam navegação até uma pasta, upload múltiplo e download de uma seleção em até três interações principais por etapa, sem depender de instruções externas.
- **SC-DRIVE-006**: Em validações responsivas, as ações essenciais de navegação, upload, seleção, download e lixeira permanecem disponíveis em telas estreitas, por toque e por teclado.
