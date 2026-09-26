# Pesquisa técnica — Perfil do usuário

## Decisões técnicas

### Nome de exibição permanece em `user`

O modelo de conta existente já possui `displayName`; a seção Perfil o atualiza diretamente. Uma tabela de perfil separada não acrescenta isolamento ou flexibilidade proporcional nesta fase.

### Avatar é um recurso privado do sistema

O avatar não usa o disco público e não cria URL externa estática. A aplicação o entrega por endpoint autenticado, autorizado pelo vínculo `USER_PROFILE_AVATAR` da fundação de arquivos. A interface pode armazenar a URL autenticada retornada pelo perfil, nunca um caminho físico.

### Recorte é pré-visualizado no cliente e validado no servidor

O navegador exibe prévia e envia a imagem de origem junto de coordenadas normalizadas de recorte. O servidor valida formato, limite de 10 MB, ambas dimensões mínimas de 400 px e coordenadas; então produz uma nova imagem de exatamente 400 × 400 px. A origem é transitória e descartada após a produção.

### GD é dependência obrigatória de processamento

Laravel e PHP do projeto não trazem biblioteca de transformação configurada. A extensão GD, compilada com JPEG, PNG e WebP, é a escolha de menor complexidade para a primeira entrega. O ambiente atual não a possui, portanto inicialização e health check devem reportar a indisponibilidade em vez de aceitar upload sem processá-lo.
