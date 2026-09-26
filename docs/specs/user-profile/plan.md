# Plano técnico — Perfil do usuário

## Resumo

A seção Perfil torna editáveis o nome de exibição e o avatar da conta. Ela usa a fundação de arquivos como único mecanismo de persistência, com imagem final privada de 400 × 400 px e sem manter a origem enviada.

## Dependências e ordem

1. Implementar migrations, serviços internos, retenção e disco privado da fundação de arquivos.
2. Disponibilizar GD com codecs JPEG, PNG e WebP no ambiente PHP de desenvolvimento, homologação e produção.
3. Implementar API de perfil e consumidor de binding gerenciada.
4. Criar a superfície de recorte, estados de envio e tratamento de erros.

## Fluxos de aplicação

### Nome

A interface envia `PATCH /api/v1/profile`. O serviço atualiza o `displayName` do usuário autenticado e devolve a representação atual. O estado de identidade compartilhado é atualizado sem reiniciar a sessão.

### Avatar

1. Cliente verifica formato, tamanho e dimensões para feedback imediato e permite escolher o quadrado de recorte.
2. Cliente envia origem e coordenadas normalizadas para `POST /api/v1/profile/avatar`.
3. Servidor valida novamente usando `fileinfo` e GD, recusa imagem com largura ou altura inferior a 400 px e gera JPEG/PNG/WebP final de 400 × 400 px conforme política definida.
4. Serviço de Perfil chama `storeManagedVersion` com finalidade `USER_PROFILE_AVATAR`.
5. A fundação ativa a nova posse, libera a anterior e atualiza consumo em transação.
6. A origem temporária é removida e o endpoint privado passa a entregar somente o resultado final.

## Estrutura prevista

| Caminho | Responsabilidade |
| --- | --- |
| `app/Http/Controllers/Api/V1/Profile` | Endpoints autenticados do contrato de perfil. |
| `app/Http/Requests/Profile` | Validação de nome, upload e geometria do recorte. |
| `app/Services/Profile` | Orquestração de nome, imagem e binding. |
| `app/Domain/Profile` | Regras de avatar e fallback de identidade. |
| `resources/js/profile` | Estado, cliente API e superfície de recorte. |
| `resources/js/design-system` | Componentes reutilizáveis de avatar, upload e diálogo. |
| `tests/Feature/Profile` | Contrato HTTP, autorização, nome e avatar. |
| `tests/Unit/Profile` | Geometria, limites e regras de fallback. |

## Contrato e superfície

O contrato HTTP está em [profile-api.md](contracts/profile-api.md). A superfície humana deve ser detalhada na próxima etapa de interface design: ela definirá layout da seção, desktop/mobile, foco, teclado, recorte, carregamento, erros e acessibilidade. Não se deve implementar o layout final apenas a partir deste plano.

## Convenções de fronteira

- A API nunca devolve caminhos físicos, hash, disco ou URL pública de storage.
- A camada de Perfil não atualiza diretamente tabelas `file_*`; ela usa o serviço interno da fundação.
- O endpoint de leitura verifica a sessão e a binding atual antes de transmitir bytes.
- A indisponibilidade de GD é um erro explícito de capacidade, não uma redução silenciosa de qualidade.

## Checagem de constituição

| Princípio | Resultado | Evidência |
| --- | --- | --- |
| Incrementalidade | PASS | Perfil consome a fundação sem antecipar drive, galeria ou compartilhamento. |
| Segurança | PASS | Upload validado duas vezes e leitura autenticada. |
| Experiência consistente | PASS | Avatar reutiliza componente central e identidade já existente. |
| Configuração externa | PASS | Limites e codecs são verificados no ambiente. |
| Qualidade verificável | PASS | Cenários de formatos, limite, recorte, privacidade e substituição definidos. |

## Próxima etapa

Executar interface design para a seção Perfil e, em paralelo, checklist de requisitos para as duas especificações antes de criar o backlog.
