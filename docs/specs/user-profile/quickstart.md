# Cenários de validação — Perfil do usuário

## Nome

1. Abra Perfil e altere o nome de exibição.
2. Confirme a persistência após recarregar a aplicação e atualização da identidade exibida na topbar.

## Avatar aceito

1. Envie JPEG, PNG e WebP de dimensões superiores a 400 px, até 10 MB.
2. Ajuste o recorte e confirme que a imagem entregue mede exatamente 400 × 400 px.
3. Substitua o avatar e confirme uma única binding ativa e nenhum original persistido.

## Avatar recusado

1. Tente enviar arquivo fora dos formatos permitidos.
2. Tente arquivo acima de 10 MB.
3. Tente imagem de 399 px em qualquer dimensão.
4. Confirme mensagens de erro específicas e ausência de arquivo final ou binding nova.

## Privacidade e remoção

1. Acesse a URL do avatar sem sessão e confirme `401`.
2. Remova o avatar; confirme retorno ao fallback de iniciais e liberação da posse gerenciada.
