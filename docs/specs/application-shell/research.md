# Pesquisa Técnica: Casca da Aplicação Autenticada

## Decisão 1: Composição compartilhada no limite autenticado

**Decisão**: evoluir a composição autenticada existente para uma casca única que recebe a identidade já carregada da sessão e expõe o conteúdo principal por composição.

**Racional**: a aplicação já concentra a leitura e a invalidação da sessão no componente raiz. Manter esse limite evita que barra, menu ou avatar consultem ou modifiquem autenticação por conta própria, preservando a fronteira de domínio e a simplicidade incremental.

**Alternativas consideradas**:

- Cada tela autenticada montar sua própria barra e consultar a sessão: descartada por duplicar comportamento e produzir divergência entre módulos futuros.
- Criar uma nova API específica de perfil: descartada porque não há perfil aprovado nem dado adicional necessário nesta fase.

## Decisão 2: Componentes pequenos com contratos explícitos

**Decisão**: introduzir componentes compartilhados para avatar, menu pessoal, painel de navegação móvel e barra superior; a casca autenticada apenas os organiza.

**Racional**: estes elementos terão uso transversal entre futuros produtos e módulos. Contratos pequenos — dados de apresentação para avatar, abertura/fechamento e eventos de ação para menus — permitem reutilização sem antecipar um sistema de módulos.

**Alternativas consideradas**:

- Concentrar toda a marcação e os estados em uma única casca: descartada porque dificultaria reutilizar avatar e área pessoal em futuras superfícies.
- Criar uma biblioteca externa de componentes: adiada; existe apenas uma interface consumidora aprovada.

## Decisão 3: Navegação móvel preparada, mas sem destinos inventados

**Decisão**: disponibilizar um painel lateral móvel com semântica, foco e fechamento completos, sem entradas de navegação até que um produto ou módulo seja especificado.

**Racional**: a estrutura resolve a adaptação responsiva agora e define um ponto de crescimento claro, sem violar o escopo autorizado com funcionalidades vazias ou atalhos enganosos.

**Alternativas consideradas**:

- Ocultar totalmente o acionador até existirem módulos: descartada porque postergaria a validação do padrão responsivo global.
- Inserir itens demonstrativos: descartada porque criaria expectativa de capacidade inexistente.

## Decisão 4: Fallback de avatar como apresentação pura

**Decisão**: derivar o texto do avatar somente do nome de exibição recebido na sessão, sem persistir iniciais, alterar dados do usuário ou fazer normalização destrutiva de caracteres.

**Racional**: o fallback é uma regra de apresentação e precisa permanecer correto para acentos e alfabetos diversos. A futura imagem de perfil poderá substituir a apresentação sem mudar o contrato do avatar.

**Alternativas consideradas**:

- Armazenar iniciais no perfil: descartada por duplicar dado derivável e exigir escopo de perfil.
- Transliterar nomes: descartada porque perde identidade e não é necessária para a regra aprovada.

## Decisão 5: Um único estado de sobreposição por vez

**Decisão**: coordenar menu pessoal e painel móvel para que apenas uma sobreposição estrutural esteja aberta de cada vez; os controles de preferências e idioma permanecem reutilizáveis dentro do menu pessoal.

**Racional**: evita sobreposição de camadas, foco ambíguo e painéis concorrentes em telas estreitas. O menu pessoal delega a cada controle existente seu próprio comportamento acessível.

**Alternativas consideradas**:

- Permitir todas as sobreposições simultaneamente: descartada por criar combinações de foco e fechamento difíceis de compreender.
- Transformar preferências e idioma em páginas: descartada porque contradiz a decisão já aprovada de controles reutilizáveis e contextuais.
