# Modelo de Dados: Identidade Visual e Preferências de Interface

## Entity: Preferência visual local

Representa as escolhas de apresentação que pertencem ao navegador nesta fase. Não é uma entidade persistida no banco de dados e não é enviada às APIs de acesso.

> [!NOTE]
> A família cromática não faz parte desta entidade nesta fase. O catálogo é estático em tokens e Rubi Industrial é aplicado quando não houver uma futura preferência de perfil.

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| versão | inteiro | obrigatório; valor conhecido | Permite invalidar com segurança formatos futuros. |
| tema | enum | `system`, `light` ou `dark` | `system` é usado apenas até haver escolha manual. |
| idioma | enum | `pt-BR`, `en`, `es` ou `fr` | O padrão é `pt-BR`. |
| escala de fonte | enum | `compact`, `default` ou `comfortable` | Controla apenas a multiplicação tipográfica. |
| densidade de espaçamento | enum | `compact`, `default` ou `comfortable` | Controla agrupamentos e espaços derivados. |
| escala de componentes | enum | `compact`, `default` ou `comfortable` | Controla campos, ícones e áreas interativas derivadas. |

### Relações

- A preferência visual local pertence a um navegador, não a uma conta nesta fase.
- O idioma selecionado determina o catálogo de conteúdo ativo.
- As três escalas são independentes e juntas alimentam os tokens calculados da interface.

### Transições de Estado

```text
ausente ou inválida -> padrão validado -> preferência atualizada -> preferência restaurada
```

## Entity: Catálogo de idioma

Representa o conjunto de textos da interface para um idioma disponível.

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| identificador | enum | um dos quatro idiomas suportados | Usado pela preferência visual local. |
| nome de exibição | texto | obrigatório | Exibido no seletor de idioma. |
| bandeira | referência visual | obrigatória | Recurso de reconhecimento no seletor; não é o único identificador do idioma. |
| mensagens | mapa de chaves | completo para conteúdo aprovado | Usa português do Brasil como fallback compreensível. |

### Relações

- Um catálogo é selecionado por uma preferência visual local.
- Cada texto apresentado em tela pertence a uma chave do catálogo correspondente.

## Entity: Escala de tokens visuais

Representa a relação central entre tokens primitivos, semânticos e de componente, por tema e por densidade.

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| camada | enum | `primitive`, `semantic` ou `component` | Proíbe o uso de valores brutos nos componentes. |
| nome | texto | único por camada | Descreve papel, não tela específica. |
| valor claro | valor visual | obrigatório quando semântico | Ativo no tema claro. |
| valor escuro | valor visual | obrigatório quando semântico | Ativo no tema escuro. |
| multiplicador de densidade | decimal | calculável quando dimensional | Derivado da preferência aplicável. |

### Relações

- Tokens de componente referenciam somente tokens semânticos ou dimensões calculadas.
- Tokens semânticos podem referenciar tokens primitivos por tema.

## Entity: Família cromática

Representa um catálogo estático de tokens, sem persistência atual, composto por dez pares claro/escuro. Os identificadores futuros são `amethyst-technical`, `architectural-teal`, `refined-copper`, `imperial-wine`, `sober-emerald`, `mineral-gold`, `orbital-indigo`, `industrial-ruby`, `deep-cyan` e `executive-coral`.

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| identificador | texto | único e estável | Preparado para a futura preferência de perfil. |
| variante clara | grupo de tokens | obrigatório | Canvas, superfície, texto, borda e ação. |
| variante escura | grupo de tokens | obrigatório | Canvas carbono neutro, superfície, texto, borda e ação. |
| padrão | booleano lógico | exatamente um | `industrial-ruby`; resolve ausência de escolha. |
