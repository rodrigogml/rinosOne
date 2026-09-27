# Especificação da Feature: Fundação de Armazenamento de Arquivos

**Feature**: `file-storage-foundation`
**Criada em**: 2026-09-26
**Status**: Planejada
**Briefing**: [Fundação de Arquivos e Perfil](../../briefing/20260925-briefing-fundacao-de-arquivos-e-perfil.md)

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| API interna de arquivos | Other | Recursos autorizados da plataforma | FULL | Criar, versionar, possuir, consultar e descartar arquivos privados de forma segura; manter árvore de pastas lógica. | Navegador de arquivos, álbuns e compartilhamento externo. |
| Perfil web | Web responsiva | Usuário autenticado | PARTIAL | Consome a fundação para manter um avatar do usuário. | Interface de drive e listagem de arquivos. |
| Consumidores futuros autorizados | Other | Usuários, tenants e integrações futuras | DEFERRED | Reutilizar posse, versão, objeto e autorização contextual. | Contratos e experiências específicas. |

## Direção de Produto

A fundação de arquivos é a única autoridade para arquivos pessoais, organizacionais e gerenciados pelo sistema. Ela
separa o arquivo lógico, suas versões, as posses de cada proprietário e o objeto físico imutável. Essa separação permite
que o mesmo conteúdo seja compartilhado ou salvo por pessoas distintas sem copiar bytes, enquanto cada uma mantém sua
própria versão atual, ciclo de vida e consumo lógico.

O primeiro consumidor será o Perfil. A extensão de drive cria somente a árvore lógica de pastas, sem navegador visual, álbum, interface de compartilhamento, thumbnail ou catálogo navegável de arquivos.

> [!IMPORTANT]
> Os nomes técnicos legados que contenham `possession`, `owner` ou “posse” identificam somente o vínculo de um arquivo com seu workspace e sua versão atual. Não existe proprietário individual de arquivo ou pasta, nem `createdBy` com efeito de autorização. A pasta e o arquivo pertencem exclusivamente ao workspace pessoal (`idUser`) ou organizacional (`idTenant`); o titular do workspace tem acesso-base, e terceiros recebem acesso somente por relations de autorização.

## Clarificações

### Sessão 2026-09-26

- Q: Pastas devem existir somente no workspace pessoal ou também no workspace organizacional? -> A: A mesma estrutura de drive deve atender workspaces de usuário e de tenant, sempre como espaços distintos.
- Q: Como excluir uma pasta que contém conteúdo? -> A: A pasta, suas subpastas e os arquivos nela contidos devem seguir juntos para a lixeira e poder ser restaurados como conjunto.

## Cenários de Usuário e Testes

### User Story 1 - Manter um arquivo para um recurso autorizado (Prioridade: P1)

Como recurso autorizado da plataforma, quero registrar conteúdo como arquivo privado de um proprietário para que uma
funcionalidade como o Perfil possa usá-lo sem criar armazenamento próprio.

**Por que esta prioridade**: uma capacidade única e segura evita que cada módulo introduza diretórios, regras de
acesso e retenção incompatíveis.

**Teste independente**: um recurso autorizado cria um arquivo gerenciado pelo sistema para uma pessoa autenticada e
consulta sua versão ativa sem que ele apareça como item de um drive.

**Cenários de aceitação**:

1. **Dado** que um recurso autorizado recebeu conteúdo válido, **quando** solicita sua criação para um proprietário,
   **então** o sistema cria arquivo lógico, versão e posse privada rastreáveis.
2. **Dado** que o arquivo tem finalidade gerenciada pelo sistema, **quando** sua posse é criada, **então** ela não fica
   disponível em listagens de workspace nem pode ser removida por operações de drive.
3. **Dado** que uma requisição falha antes de concluir a associação do arquivo ao recurso, **quando** a operação termina,
   **então** não permanece consumo lógico sem posse nem versão utilizável sem referência válida.

---

### User Story 2 - Reutilizar conteúdo e preservar versões independentes (Prioridade: P1)

Como proprietário de um arquivo ou consumidor autorizado, quero que conteúdo idêntico seja reutilizado fisicamente e
que uma edição crie uma nova versão sem alterar a versão de outros proprietários.

**Por que esta prioridade**: deduplicação reduz espaço usado, enquanto posse e versões independentes preservam controle
e possibilidade de recuperação.

**Teste independente**: duas posses distintas usam conteúdo idêntico e ocupam um único objeto físico; uma delas cria
uma nova versão e a outra continua apontando para a versão anterior.

**Cenários de aceitação**:

1. **Dado** que duas solicitações autorizadas enviam bytes idênticos, **quando** as criações são concluídas, **então**
   cada proprietário possui seu registro lógico e consumo próprio, mas ambos referenciam o mesmo objeto físico.
2. **Dado** que duas posses apontam para a mesma versão, **quando** uma posse cria uma versão derivada, **então** sua
   versão atual muda sem modificar a posse nem a versão atual da outra pessoa.
3. **Dado** que duas novas versões derivam da mesma origem, **quando** ambas são concluídas, **então** o sistema preserva
   a origem de cada ramificação sem supor uma sequência linear entre elas.
4. **Dado** que uma posse deixa de existir, **quando** outra posse ainda referencia a mesma versão ou objeto, **então**
   o conteúdo permanece disponível somente para os proprietários remanescentes autorizados.

---

### User Story 3 - Excluir, restaurar e liberar consumo com segurança (Prioridade: P1)

Como proprietário, quero mover minha posse para a lixeira, restaurá-la ou removê-la definitivamente para controlar meu
consumo sem apagar arquivos que ainda pertencem a outras pessoas.

**Por que esta prioridade**: a exclusão precisa proteger contra perda acidental e respeitar compartilhamentos e backups.

**Teste independente**: uma posse é enviada para a lixeira, restaurada, esvaziada e finalmente deixa de consumir quota;
uma segunda posse do mesmo conteúdo permanece íntegra durante todo o fluxo.

**Cenários de aceitação**:

1. **Dado** que possuo um arquivo ativo, **quando** o envio para a lixeira é aceito, **então** ele permanece recuperável
   por 30 dias e continua consumindo minha quota lógica.
2. **Dado** que possuo um item na lixeira ainda válido, **quando** o restauro, **então** recupero minha posse e sua versão
   atual sem criar cópia física desnecessária.
3. **Dado** que esvazio a lixeira ou o prazo expira, **quando** a remoção é concluída, **então** somente minha posse é
   removida de forma irrecuperável e deixa de consumir minha quota.
4. **Dado** que não existe mais posse ou referência válida para uma versão, **quando** ela cumpre a retenção técnica,
   **então** torna-se elegível à remoção física sem comprometer uma restauração de backup permitida.

---

### User Story 4 - Conservar e reconciliar o acervo (Prioridade: P2)

Como equipe de operação, quero que a plataforma aplique políticas de retenção, compactação e reconciliação para manter
o acervo íntegro, eficiente e restaurável.

**Por que esta prioridade**: o custo de armazenamento e o risco de divergência entre catálogo e volumes crescem com o
tempo, mesmo antes da existência de um drive visível.

**Teste independente**: uma política de compactação é alterada, uma versão elegível é reprocessada sem mudar sua
identidade lógica e uma verificação identifica objetos órfãos sem expor arquivos a usuários.

**Cenários de aceitação**:

1. **Dado** que uma política define compactação para um tipo de arquivo, **quando** uma nova versão desse tipo é aceita,
   **então** sua representação armazenada segue a política sem alterar os bytes lógicos entregues ao consumidor.
2. **Dado** que a política de compactação muda, **quando** versões existentes são reprocessadas, **então** elas preservam
   identidade, posse, integridade e autorização enquanto a nova representação passa a ser usada.
3. **Dado** que um objeto físico não possui registro ou referência válida no catálogo, **quando** a reconciliação o
   encontra, **então** ele é identificado para limpeza segura sem inferir proprietário pelo caminho físico.
4. **Dado** que o catálogo restaurado referencia uma versão histórica, **quando** a retenção técnica ainda é aplicável,
   **então** o objeto físico correspondente permanece recuperável.

### Casos de Borda

- Um conteúdo idêntico enviado por proprietários sem relação não pode revelar a existência, nome, hash, versão ou posse
  de nenhum deles.
- A mesma pessoa pode remover sua posse de conteúdo compartilhado sem remover a posse de outra pessoa.
- Uma edição cria nova versão mesmo que a origem seja compartilhada; a origem só é elegível à limpeza depois de não ter
  posses nem retenções aplicáveis.
- O caminho físico nunca usa nome de usuário, tenant, pasta lógica, nome original ou permissão como fonte de autorização.
- Arquivos de sistema seguem o mesmo catálogo e contabilização, mas só o recurso controlador pode alterar ou remover sua
  posse.
- Falha, repetição ou interrupção de processamento não pode resultar em versão parcialmente ativa, dupla cobrança de
  quota ou objeto sem possibilidade de reconciliação.

## Requisitos

### Requisitos Funcionais

- **FR-FILE-001**: O sistema DEVE manter separadamente o arquivo lógico, sua versão, a posse de cada proprietário e a
  representação física do conteúdo.
- **FR-FILE-002**: Cada posse DEVE identificar seu proprietário, escopo pessoal, organizacional ou gerenciado pelo
  sistema, sua versão atual, estado e consumo lógico associado.
- **FR-FILE-003**: O sistema DEVE permitir que uma versão indique sua versão de origem e DEVE permitir ramificações de
  versões derivadas da mesma origem.
- **FR-FILE-004**: O sistema DEVE deduplicar globalmente representações físicas com conteúdo idêntico, sem compartilhar
  automaticamente posse, autorização, nome, localização lógica ou qualquer metadado entre proprietários.
- **FR-FILE-005**: O sistema DEVE contabilizar o consumo lógico de cada posse, inclusive quando a representação física
  for compartilhada, e apresentar subtotais de workspace, arquivos gerenciados pelo sistema, lixeira e total.
- **FR-FILE-006**: O sistema DEVE manter o arquivo privado por padrão e conceder leitura somente a um contexto de uso
  autorizado. Links externos públicos ou compartilháveis não fazem parte desta feature.
- **FR-FILE-007**: O sistema DEVE permitir ativos `system-managed` vinculados a um recurso da plataforma, fora das
  listagens de workspace e sem remoção pelas operações comuns de arquivos.
- **FR-FILE-008**: O sistema DEVE manter uma lixeira por posse, com retenção padrão de 30 dias configurável por ambiente.
  Itens nela continuam consumindo quota até o restauro, expiração ou limpeza explícita.
- **FR-FILE-009**: O sistema DEVE permitir a limpeza explícita de uma posse na lixeira, removendo-a de forma
  irrecuperável para seu proprietário e liberando seu consumo lógico sem afetar outras posses.
- **FR-FILE-010**: O sistema DEVE reter objetos e versões sem referência pelo período técnico mínimo configurável,
  nunca inferior ao período de retenção dos backups aplicável à instância.
- **FR-FILE-011**: O sistema DEVE armazenar objetos físicos de forma imutável em uma árvore técnica fragmentada por hash,
  sem usar dados de proprietário, tenant, pasta ou nome original no caminho.
- **FR-FILE-012**: O sistema DEVE manter checksum lógico, checksum da representação armazenada, tamanho lógico, tamanho
  armazenado, MIME type detectado, extensão declarada e codificação de compactação quando aplicável.
- **FR-FILE-013**: O sistema DEVE aplicar política de compactação configurável por MIME type e extensão às novas versões
  elegíveis e DEVE permitir reprocessamento retroativo seguro após mudança de política.
- **FR-FILE-014**: O sistema DEVE permitir metadados extensíveis vinculados a uma versão, incluindo data/hora, dispositivo,
  orientação e localização quando presentes em imagens ou vídeos.
- **FR-FILE-015**: O sistema DEVE usar um backend privado local configurado por ambiente nesta fase e DEVE manter o
  catálogo capaz de identificar o backend de cada representação sem exigir movimentação de objetos existentes para
  acrescentar novos backends.
- **FR-FILE-016**: O sistema DEVE validar a integridade entre catálogo e armazenamento, identificando referências
  quebradas e objetos órfãos para tratamento seguro.
- **FR-FILE-017**: A fundação DEVE preservar capacidade de gerar representações derivadas, incluindo thumbnails, sem
  gerar, listar ou entregar thumbnails nesta fase.
- **FR-FILE-018**: A feature DEVE expor operações por API interna versionada para consumidores autorizados e NÃO DEVE
  depender de uma interface web específica.
- **FR-FILE-019-INFRA-SCHED**: O sistema DEVE executar de forma automática e configurável a expiração de lixeira,
  limpeza elegível, reconciliação e reprocessamento de compactação; a elegibilidade lógica deve respeitar os prazos
  exatos mesmo que a execução periódica ocorra depois deles.
- **FR-FILE-020-INFRA-BACKUP**: A configuração do ambiente DEVE definir retenção de backup e retenção técnica de arquivos;
  o sistema DEVE rejeitar configuração em que a retenção técnica seja inferior à de backup.
- **FR-FILE-021**: Esta feature NÃO DEVE criar navegador visual de drive, álbum, busca de arquivos, compartilhamento externo,
  thumbnail entregue ao usuário, interface administrativa de backends nem bloqueio de upload por quota.
- **FR-FILE-022**: O sistema DEVE manter pastas lógicas para workspaces pessoais e de tenant, com proprietário exclusivo, hierarquia interna e raiz implícita por workspace.
- **FR-FILE-023**: A remoção de uma pasta DEVE enviar recursivamente suas subpastas e posses de arquivo à lixeira, preservando a capacidade de restauro conjunto e as retenções aplicáveis.
- **FR-FILE-024**: Uma pasta enviada à lixeira DEVE possuir o mesmo prazo de retenção configurável das posses que compõem seu conjunto; o restauro só é permitido enquanto todo o conjunto permanecer restaurável.

### Entidades Principais

- **Arquivo lógico**: identidade estável de um conteúdo pertencente a uma ou mais posses, independente do nome e da
  localização futura de cada proprietário.
- **Versão de arquivo**: estado imutável de conteúdo dentro de um arquivo lógico, com origem opcional e relações de
  ramificação.
- **Posse de arquivo**: vínculo entre proprietário e versão atual, que define estado, escopo, finalidade, visibilidade
  no workspace e consumo lógico.
- **Representação física**: objeto imutável que contém bytes armazenados, backend, checksums, tamanho e compactação.
- **Metadado de versão**: informação extensível extraída ou atribuída ao conteúdo de uma versão.
- **Política de retenção**: prazo de lixeira e prazo técnico que determinam quando uma posse, versão ou representação
  pode ser limpa.
- **Backend de armazenamento**: ponto privado configurado pela infraestrutura onde representações são gravadas.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-FILE-001**: 100% dos testes aprovados de conteúdo idêntico confirmam uma única representação física e posses
  lógicas independentes para cada proprietário.
- **SC-FILE-002**: 100% dos testes aprovados de edição compartilhada preservam a versão de origem e mantêm a versão atual
  de cada posse independente.
- **SC-FILE-003**: 100% dos testes aprovados de lixeira confirmam retenção de 30 dias, consumo durante a lixeira,
  restauro correto e liberação de consumo após limpeza ou expiração.
- **SC-FILE-004**: 100% dos testes aprovados de retenção impedem limpeza física antes do maior prazo aplicável entre
  retenção técnica e backup.
- **SC-FILE-005**: 100% dos testes aprovados de autorização confirmam que uma posse não revela nem concede leitura de
  arquivo, versão ou objeto pertencente apenas a outro proprietário.
