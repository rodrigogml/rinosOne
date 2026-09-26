# Especificação da Feature: Perfil do Usuário

**Feature**: `user-profile`
**Criada em**: 2026-09-26
**Status**: Planejada
**Dependência**: [Fundação de Armazenamento de Arquivos](../file-storage-foundation/spec.md)

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| Configurações do usuário | Web responsiva | Usuário autenticado e validado | FULL | Alterar nome de exibição, definir, substituir ou remover avatar. | Dados pessoais adicionais, senha, dois fatores, preferências visuais e sessões, que mantêm seus próprios fluxos. |
| Consumidores futuros autorizados | Other | Aplicações e módulos autorizados | DEFERRED | Ler nome e avatar vigentes somente conforme autorização. | Experiência e contratos de outros clientes. |

## Direção de Produto

Perfil é uma seção das Configurações do usuário. Ela permite que a pessoa mantenha o mesmo nome de exibição definido no
cadastro e uma imagem de avatar consistente na topbar e em futuros contextos autorizados. O avatar é um ativo privado
gerenciado pelo sistema, sustentado pela fundação de arquivos; não aparece no drive nem depende de uma área própria de
armazenamento.

## Clarificações

### Sessão 2026-09-26

- Q: Qual regra de dimensão deve ser aplicada ao avatar? -> A: A fonte pode ser maior para permitir o recorte, mas deve
  possuir ambos os lados com ao menos 400 px. O resultado persistido é sempre quadrado em 400 × 400 px; imagens menores
  são recusadas e nunca sofrem upscale.
- Q: Quais formatos e tamanho máximo devem ser aceitos para avatar? -> A: JPEG, PNG e WebP, até 10 MB.

## Cenários de Usuário e Testes

### User Story 1 - Alterar o nome de exibição (Prioridade: P1)

Como usuário autenticado, quero alterar meu nome de exibição para que a plataforma me identifique corretamente nas
interfaces autorizadas.

**Por que esta prioridade**: o nome é parte da identidade já usada pela sessão e precisa poder evoluir após o cadastro.

**Teste independente**: uma pessoa altera o nome por Perfil, atualiza a página e confirma que o novo nome continua
presente na identidade exibida.

**Cenários de aceitação**:

1. **Dado** que estou autenticado, **quando** informo um nome de exibição válido e confirmo a alteração, **então** o
   sistema atualiza minha identidade e passa a exibir o novo nome nas áreas autorizadas.
2. **Dado** que informo nome vazio ou inválido, **quando** tento salvar, **então** recebo orientação no campo e meu nome
   anterior permanece inalterado.
3. **Dado** que alterei o nome com sucesso, **quando** atualizo a sessão ou abro nova área autenticada, **então** a mesma
   identidade permanece disponível sem exigir novo login.

---

### User Story 2 - Definir e substituir o avatar (Prioridade: P1)

Como usuário autenticado, quero selecionar e enquadrar uma imagem quadrada para ter um avatar que me represente nas
interfaces da plataforma.

**Por que esta prioridade**: o avatar melhora reconhecimento pessoal sem criar um serviço de imagens específico ou
expor arquivos pessoais.

**Teste independente**: uma pessoa envia imagem permitida, ajusta seu enquadramento quadrado, salva e confirma que a
imagem aparece na topbar após recarregar a aplicação.

**Cenários de aceitação**:

1. **Dado** que estou na seção Perfil, **quando** escolho uma imagem permitida, **então** consigo ajustar posição e zoom
   em uma área quadrada antes de confirmar.
2. **Dado** que confirmo o recorte, **quando** o salvamento é concluído, **então** a imagem quadrada resultante em
   400 × 400 px torna-se meu único avatar ativo e aparece nas interfaces autorizadas.
3. **Dado** que já possuo avatar, **quando** confirmo outro, **então** o avatar novo substitui o anterior sem expor ou
   deixar o anterior como arquivo acessível no meu workspace.
4. **Dado** que cancelo o editor ou ocorre uma falha antes da confirmação, **quando** retorno ao Perfil, **então** o
   avatar anterior permanece ativo e nenhum avatar parcial é apresentado.

---

### User Story 3 - Remover avatar e manter identificação acessível (Prioridade: P2)

Como usuário autenticado, quero remover meu avatar para que a plataforma volte à identificação textual derivada do meu
nome sem manter imagem anterior em uso.

**Por que esta prioridade**: a imagem é opcional e sua ausência não pode quebrar a identificação da pessoa.

**Teste independente**: uma pessoa com avatar ativo o remove, atualiza a aplicação e encontra o fallback textual correto
em vez da imagem anterior.

**Cenários de aceitação**:

1. **Dado** que possuo avatar ativo, **quando** confirmo sua remoção, **então** a plataforma remove a associação de
   avatar e apresenta o fallback derivado do meu nome.
2. **Dado** que removi o avatar, **quando** ele deixa de ter posse ativa, **então** ele não continua consumindo quota como
   ativo gerenciado pelo sistema, respeitando apenas as retenções técnicas da fundação.
3. **Dado** que não possuo avatar ou a imagem não pode ser carregada, **quando** vejo minha identidade, **então** o
   fallback textual permanece disponível com nome acessível.

### Casos de Borda

- O Perfil exige sessão autenticada e não pode permitir mudança de identidade de outra conta.
- Substituir ou remover avatar não pode deixar duas imagens ativas para a mesma pessoa.
- O avatar nunca é listado como arquivo de workspace, nem pode ser removido por uma ação futura do drive.
- Falha de rede, validação ou processamento preserva nome e avatar anteriormente confirmados.
- Uma imagem cuja largura ou altura seja menor que 400 px é recusada; o sistema não amplia imagens de origem.
- O resultado do editor é o único conteúdo persistido; o original temporário enviado para recorte não é mantido como
  arquivo do usuário.
- O fallback de avatar segue as regras já aprovadas para nome composto, nome único e ausência de nome.

## Requisitos

### Requisitos Funcionais

- **FR-PROFILE-001**: O sistema DEVE disponibilizar a seção Perfil dentro das Configurações do usuário para toda pessoa
  autenticada e validada.
- **FR-PROFILE-002**: O sistema DEVE permitir alterar o nome de exibição, aplicando as mesmas regras de validade exigidas
  no cadastro e preservando o valor anterior quando a alteração for recusada.
- **FR-PROFILE-003**: O sistema DEVE refletir um nome alterado nas identidades autorizadas sem exigir novo login.
- **FR-PROFILE-004**: O sistema DEVE aceitar somente JPEG, PNG ou WebP de até 10 MB, cuja largura e altura sejam de pelo
  menos 400 px; deve permitir origem maior para recorte e persistir somente avatar quadrado em 400 × 400 px. Imagens
  menores DEVEM ser recusadas e nunca sofrer upscale.
- **FR-PROFILE-005**: Antes de persistir o avatar, o sistema DEVE permitir que a pessoa defina posição e zoom em um
  enquadramento quadrado reutilizável.
- **FR-PROFILE-006**: O sistema DEVE persistir somente o resultado quadrado confirmado do editor, sem manter o original
  transitório como arquivo pessoal, de workspace ou ativo do sistema.
- **FR-PROFILE-007**: O avatar ativo DEVE ser um ativo privado gerenciado pelo sistema e NÃO DEVE aparecer em listagens
  de workspace nem ser removível por operações gerais de arquivos.
- **FR-PROFILE-008**: Ao definir um novo avatar, o sistema DEVE tornar a nova imagem a única associação ativa somente após
  concluir sua validação e persistência; a associação anterior deve ser removida de forma segura.
- **FR-PROFILE-009**: O sistema DEVE permitir remover o avatar ativo e deve retornar imediatamente ao fallback textual
  de identidade.
- **FR-PROFILE-010**: O avatar DEVE ser exibido somente em contexto autenticado e autorizado nesta fase; links externos,
  avatares publicamente acessíveis e compartilhamento não são permitidos.
- **FR-PROFILE-011**: Nome, avatar, fallback e erros de validação DEVEM operar nos quatro idiomas e respeitar contraste,
  teclado, toque, leitor de tela, densidades e responsividade já aprovados.
- **FR-PROFILE-012**: A feature DEVE usar a fundação de arquivos para salvar, substituir, remover e contabilizar avatar;
  ela NÃO DEVE criar diretório, armazenamento, quota ou ciclo de retenção próprio.
- **FR-PROFILE-013**: A feature NÃO DEVE introduzir perfil público, dados pessoais adicionais, senha, recuperação de
  senha, dois fatores, drive, álbuns, thumbnails ou compartilhamento externo.

### Entidades Principais

- **Perfil do usuário**: conjunto de identidade pessoal editável composto, nesta fase, pelo nome de exibição e pela
  associação opcional ao avatar ativo.
- **Avatar ativo**: ativo `system-managed` privado associado a um único Perfil e à sua versão de imagem quadrada atual
  em 400 × 400 px.
- **Recorte de avatar**: enquadramento quadrado confirmado pela pessoa antes de a imagem resultante se tornar avatar.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-PROFILE-001**: 100% dos testes aprovados de alteração de nome preservam o valor anterior em erro e exibem o novo
  valor em uma nova área autenticada após sucesso.
- **SC-PROFILE-002**: 100% dos testes aprovados de substituição de avatar confirmam exatamente uma associação ativa e
  nenhuma imagem parcial visível após falha ou cancelamento; toda imagem persistida possui 400 × 400 px e nenhuma imagem
  menor é ampliada.
- **SC-PROFILE-003**: 100% dos testes aprovados de remoção de avatar confirmam fallback textual imediato e ausência de
  consumo lógico ativo do avatar removido.
- **SC-PROFILE-004**: 100% dos fluxos aprovados em desktop, tablet e telefone permitem editar nome e avatar sem overflow
  horizontal, perda de foco ou dependência exclusiva de cor ou ícone.
