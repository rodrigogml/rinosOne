# Cenários de validação — Rinos Drive

## Rinos Drive Pessoal: navegação e upload

1. Autentique um usuário e abra Rinos Drive Pessoal.
2. Crie `Documentos`, entre nela e envie dois arquivos com o mesmo nome.
3. Repita dois envios simultâneos para a mesma pasta, também com o mesmo nome.
4. **Esperado**: todos os itens aparecem com nomes finais distinguíveis e únicos; nenhum substitui outro.
5. Alterne grade, lista, detalhes e tabela.
6. **Esperado**: a coleção é a mesma em todas as apresentações e o painel lateral mostra apenas metadados seguros.

## Rinos Drive Work: administrador e membro delimitado

1. Crie uma organização com administrador A e membro B.
2. No Drive Work, A cria `Financeiro/Contratos` e `Produto`.
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
