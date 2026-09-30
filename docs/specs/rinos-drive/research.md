# Pesquisa técnica — Rinos Drive

## Decisão 1 — Um navegador, dois alvos de workspace

**Decisão**: o módulo terá uma única superfície de navegador que recebe um alvo tipado: pessoal ou organizacional. O alvo pessoal é resolvido pelo usuário autenticado; o organizacional deriva do tenant ativo da instância da janela.

**Racional**: a estrutura de pastas, posses e retenção é a mesma nos dois workspaces. Duplicar a interface criaria divergência sem benefício e permitir que o cliente informe um proprietário arbitrário enfraqueceria o isolamento.

**Alternativas consideradas**:

- Duas telas independentes: rejeitada por duplicação de comportamento e contratos.
- Um identificador de workspace livre no cliente: rejeitada por permitir tentativas de enumeração e acesso indevido.

## Decisão 2 — Acesso organizacional por relação de pasta

**Decisão**: administradores ativos de tenant acessam todo o Drive Work; outros membros só acessam uma pasta quando recebem relação `READ` ou `EDIT`, direta ou por grupo, herdada por descendentes. A ausência de relação resulta em estado vazio neutro.

**Racional**: membership habilita contexto organizacional, mas não é autorização para acervo. A estrutura já possui relações herdáveis de recurso e deve continuar sendo a fonte de verdade.

**Alternativas consideradas**:

- Conceder acesso total a qualquer membro: rejeitada por contrariar o isolamento por objeto.
- Criar uma nova hierarquia de ACL exclusiva do Drive: rejeitada por duplicar autorização, auditoria e regras de revogação.

## Decisão 3 — Administrador antes da relação de recurso

**Decisão**: a decisão de acesso a pasta organizacional preservará a precedência de restrições e reconhecerá administrador ativo do tenant como principal integral do workspace antes de exigir relation. As permissions de pasta continuam significando ação sobre recurso, não uma concessão global para roles comuns.

**Racional**: o catálogo de autorização já determina que `tenant.administrator` recebe permissions de tenant novas. A avaliação atual de recurso precisa refletir essa garantia sem converter grants de roles comuns em acesso a todas as pastas.

**Alternativas consideradas**:

- Conceder relations individuais aos administradores em toda pasta: rejeitada por manutenção, risco de lacunas e custo ao criar árvores grandes.
- Tratar qualquer role com permission de pasta como acesso integral: rejeitada, pois viola a regra de acesso por objeto para membros não administrativos.

## Decisão 4 — Exportação múltipla efêmera e privada

**Decisão**: uma seleção múltipla cria uma exportação privada, temporária e não navegável. Ela expira em 60 minutos por padrão e não integra o consumo de quota do workspace.

**Racional**: ZIPs de exportação são derivados operacionais e não documentos que o usuário decidiu guardar. Mantê-los no Drive gera lixo, interfere na cota e amplia retenção e backup sem valor de produto.

**Alternativas consideradas**:

- Gravar o ZIP como arquivo do usuário: rejeitada por tornar limpeza e custo responsabilidade indevida do usuário.
- Gerar o ZIP durante a mesma requisição: rejeitada por falhar mal com seleções grandes e impedir feedback confiável.

## Decisão 5 — Conflito preserva ambos os itens

**Decisão**: upload, criação e movimentação com nome ocupado recebem nome automaticamente distinguível; a resolução e reserva do nome final são serializadas por workspace e localização de destino, dentro da mesma operação atômica. Não há substituição silenciosa nem criação automática de versão do item existente.

**Racional**: uma nova versão representa edição deliberada de uma linhagem, enquanto upload e movimentação são operações de organização. Preservar ambos evita perda de dados e funciona em lote.

**Alternativas consideradas**:

- Sobrescrever: rejeitada por risco de perda e semântica incorreta de versão.
- Solicitar decisão item a item: rejeitada por degradar upload em lote e automações futuras.

## Decisão 6 — Sem thumbnails nesta entrega

**Decisão**: grade e lista usam ícones por tipo; o painel mostra metadados seguros. Thumbnails, previews e players continuam adiados.

**Racional**: a fundação já reserva derivadas, mas geração e entrega de mídia têm custo operacional e requisitos próprios de segurança e desempenho.

**Alternativas consideradas**:

- Gerar miniaturas apenas para imagens: rejeitada por criar comportamento inconsistente e antecipar uma cadeia de processamento ainda não especificada.

## Decisão 7 — Catálogo unificado e lazy de drives

**Decisão**: uma janela global obtém um catálogo filtrado de drives acessíveis e carrega cada árvore/localização somente quando o painel navega até ela. A organização ativa em outros módulos não é entrada desse catálogo.

**Racional**: uma única ferramenta reduz duplicação visual e permite comparar workspaces. Carregar todas as árvores antecipadamente ampliaria latência e exposição de metadados; membership isolada não é suficiente para exibir uma raiz Work.

**Alternativas consideradas**:

- Continuar com uma janela por organização ativa: rejeitada por duplicar acesso e impedir comparação natural entre drives.
- Carregar todos os arquivos de todos os drives ao abrir: rejeitada por custo e risco de enumeração.

## Decisão 8 — Arquivo como recurso compartilhável somente leitura

**Decisão**: `file_filePossession` recebe adapter de autorização próprio para relação direta `READ`. O item aparece em Compartilhados comigo e pode ser baixado, exportado ou copiado, mas não sofre alteração no workspace de origem.

**Racional**: compartilhar um arquivo isolado não pode conceder navegação à pasta, nem permitir que o destinatário modifique o acervo de outro responsável. A futura edição online terá contrato e lifecycle independentes.

**Alternativas consideradas**:

- Exigir que todo compartilhamento seja de pasta: rejeitada por não atender a necessidade de encontrar um arquivo isolado.
- Conceder `EDIT` direto no arquivo: rejeitada nesta fase por misturar colaboração de conteúdo com operação de organização.

## Decisão 9 — Transferência inter-drive é lógica e assíncrona

**Decisão**: cópia/movimento entre drives usa uma operação persistente em fila. Ela cria ou libera posses e referências sem mover bytes de `file_fileContent`; a janela somente acompanha progresso seguro.

**Racional**: os workspaces usam a mesma fundação deduplicada. Um job persistente mantém a operação ativa após fechar a interface e permite árvore grande, retry e revalidação sem bloquear a requisição HTTP.

**Alternativas consideradas**:

- Copiar bytes entre backends: rejeitada por contrariar deduplicação e gerar custo sem valor.
- Executar toda transferência na requisição: rejeitada por timeout, indisponibilidade e ausência de recuperação.

## Decisão 10 — Reserva de ramo com lease renovável

**Decisão**: uma transferência reserva os ramos de origem e destino de forma exclusiva antes de executar. A reserva tem lease renovável; rotina agendada recupera ou falha operações abandonadas e libera os ramos.

**Racional**: o bloqueio lógico evita que operações concorrentes alterem parte de uma árvore que já está comprometida, sem manter transação de banco aberta durante todo o job. O lease evita bloqueio eterno após falha de worker.

**Alternativas consideradas**:

- Transação aberta até terminar a transferência: rejeitada por locks longos, deadlocks e indisponibilidade.
- Lock em cache sem persistência: rejeitada por não sobreviver reinício nem oferecer auditoria/recovery.
- Liberação manual por administrador: rejeitada por risco operacional e custo desnecessário.

## Decisão 11 — Revalidar autorização antes da confirmação

**Decisão**: origem e destino são autorizados ao solicitar a operação e imediatamente antes do commit lógico. Revogação no intervalo falha a transferência sem ativar destino parcial ou remover a origem.

**Racional**: reserva protege consistência, não substitui autorização. A segunda decisão preserva o princípio de revogação efetiva na próxima operação relevante.

**Alternativas consideradas**:

- Concluir com a autorização da solicitação: rejeitada por permitir alteração depois de revogação.
- Impedir revogação enquanto houver operação: rejeitada por transformar operação de arquivo em bloqueio administrativo.
