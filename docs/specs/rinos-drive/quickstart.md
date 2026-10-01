# Cenários de validação — Rinos Drive

## Catálogo unificado e compartilhados comigo

1. Autentique um usuário com Meu Drive, uma organização administrada, uma pasta recebida em outra organização e um arquivo recebido diretamente.
2. Abra a ferramenta global Rinos Drive, sem selecionar organização na topbar.
3. **Esperado**: a árvore mostra Meu Drive, somente os Drives Work efetivamente acessíveis e Compartilhados comigo; organização sem relação aplicável não aparece.
4. Abra Compartilhados comigo e selecione o arquivo diretamente concedido.
5. **Esperado**: o item informa origem segura, permite baixar, exportar ou copiar, mas não oferece renomear, mover, lixeira ou substituição.
6. Revogue a relação e atualize a raiz virtual.
7. **Esperado**: o item desaparece sem revelar pasta, irmãos ou caminho de origem.

## Dois painéis e transferência lógica

1. Abra Meu Drive no painel esquerdo e um Drive Work editável no painel direito.
2. Arraste um arquivo do esquerdo para uma pasta do direito.
3. **Esperado**: surge confirmação com Cancelar, Copiar e Mover; Copiar é a opção inicial por serem drives distintos.
4. Confirme Copiar, feche a janela e consulte novamente o Rinos Drive.
5. **Esperado**: a operação continua em segundo plano, termina com a posse no destino e mantém a origem; nenhum byte físico é duplicado.
6. Repita com modo Mover.
7. **Esperado**: a origem só é liberada após o destino estar íntegro, com quota, retenção e permissões próprias do destino.
8. Arraste entre duas pastas do mesmo drive.
9. **Esperado**: o mesmo diálogo aparece, mas Mover é a opção inicial.

## Reserva, recuperação e revogação de transferência

1. Inicie o movimento de uma pasta grande entre dois drives autorizados e retenha o job em processamento.
2. Tente criar, renomear, mover ou enviar à lixeira um item dentro do ramo reservado, a partir de outra sessão.
3. **Esperado**: a segunda operação retorna erro seguro de transferência em andamento e não altera a árvore.
4. Revogue a relação de origem ou destino antes da confirmação lógica e libere o job.
5. **Esperado**: a transferência falha sem item ativo parcial no destino e sem remover a origem.
6. Interrompa o worker antes da confirmação e aguarde o lease configurado.
7. **Esperado**: a manutenção recupera idempotentemente ou falha a operação e libera as reservas; nenhum ramo permanece bloqueado.

## Limites de seleção de transferência

1. **Dado** um ambiente com limites configurados de itens e profundidade, prepare uma seleção que exceda cada limite individualmente.
2. **Quando** o usuário confirmar Copiar ou Mover, a criação da transferência é recusada antes de persistir operação, reserva ou job.
3. **Esperado**: a resposta traz erro categorizado seguro, a origem e o destino não são modificados e uma nova seleção dentro dos limites continua disponível.

## Meu Drive: navegação e upload

1. Autentique um usuário e abra a ferramenta global Rinos Drive; selecione Meu Drive no catálogo.
2. Crie `Documentos`, entre nela e envie dois arquivos com o mesmo nome.
3. Repita dois envios simultâneos para a mesma pasta, também com o mesmo nome.
4. **Esperado**: todos os itens aparecem com nomes finais distinguíveis e únicos; nenhum substitui outro.
5. Alterne grade, lista, detalhes e tabela.
6. **Esperado**: a coleção é a mesma em todas as apresentações e o painel lateral mostra apenas metadados seguros.

## Drive Work: administrador e membro delimitado

1. Crie uma organização com administrador A e membro B.
2. No Rinos Drive, A seleciona o Drive Work e cria `Financeiro/Contratos` e `Produto`.
3. Conceda a B somente leitura em `Financeiro`.
4. Abra a organização como B.
5. **Esperado**: B navega `Financeiro/Contratos`, pode baixar conteúdo legível e não vê `Produto` nem ações de alteração.
6. Revogue a relação e atualize a localização.
7. **Esperado**: B deixa de acessar o ramo sem obter nome, contagem ou conteúdo residual.

## Exportação múltipla

1. Em uma pasta legível, selecione arquivos e subpastas autorizados.
2. Solicite download múltiplo e aguarde o estado pronto.
3. Baixe o pacote antes do prazo.
4. **Esperado**: o pacote contém somente a seleção, preserva a hierarquia e não contém caminhos internos.
5. Aguarde o prazo configurado ou force a expiração em ambiente de teste.
6. **Esperado**: o download deixa de estar disponível e os bytes temporários são removidos pela rotina de limpeza.

## Roundtrip interface–API

1. Pela interface, abra uma pasta pessoal e envie dois arquivos.
2. Capture a resposta de upload e valide que cada item contém somente a projeção contratada.
3. Atualize a localização pelo contrato real.
4. **Esperado**: os itens retornados têm ids, nomes, tamanho, tipo, data e capabilities; não retornam hash, chave de storage, caminho físico ou URL pública.

## Evidência automatizada

Os cenários são exercitados de forma automatizada pelos testes de feature, interface e E2E abaixo; a inspeção manual de homologação continua sendo a confirmação final de composição visual com uma sessão real.

| Cenário | Evidência |
| --- | --- |
| Pessoal, nomes, upload e atualização | `DriveWorkspaceUploadApiTest`, `DriveWorkspaceCommandApiTest` e `DriveExplorer.spec.ts` |
| Work administrativo, relação parcial e revogação | `DriveWorkspaceProjectionApiTest` e `DriveWorkspaceDownloadApiTest` |
| Exportação, ZIP, expiração, limite, cancelamento e limpeza | `WorkspaceExportPersistenceTest` e `WorkspaceExportScheduleConfigurationTest` |
| Transferência, reserva, recuperação e contrato privado | `DriveTransferRequestServiceTest`, `WorkspaceTransferExecutionServiceTest`, `ProcessWorkspaceTransferTest`, `WorkspaceTransferRecoveryServiceTest` e `DriveTransferApiTest` |
| Contrato e apresentação responsiva | `driveWorkspaceApi.spec.ts` e `DriveExplorer.spec.ts` |
| Composição autenticada em desktop/telefone e revogação visual | `application.spec.ts` — cenário `rechecks a Drive folder location and preserves the workspace after revocation on desktop and telephone` |
