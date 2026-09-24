# Modelo de Dados de Interface: Casca da Aplicação Autenticada

Esta feature não cria persistência, migrações ou entidades de domínio. Os modelos abaixo são transitórios na interface e derivam de dados de sessão e de preferências locais já existentes.

## Entidade: Usuário autenticado para apresentação

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `displayName` | texto | Pode estar ausente ou vazio na apresentação defensiva | Vem da sessão autenticada existente; é a fonte para o fallback do avatar. |
| `avatarSource` | endereço opcional | Ausente nesta fase | Reservado para futura imagem de perfil; não cria upload, armazenamento ou contrato novo. |

### Relacionamentos

- Um usuário autenticado é exibido por uma única casca autenticada por contexto aberto da aplicação.
- O nome de exibição pode gerar uma apresentação de avatar, mas essa apresentação não é persistida.

## Entidade: Apresentação do avatar

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `kind` | enum | `image`, `initials` ou `unknown` | Define se a superfície mostra imagem, texto derivado ou interrogação. |
| `label` | texto | Sempre possui valor apresentável | Contém as iniciais derivadas ou `?`; também apoia o rótulo acessível. |
| `imageSource` | endereço opcional | Presente somente com `kind=image` | Não é produzido nesta fase. |

### Transições de estado

```text
nome ausente -> unknown
nome disponível -> initials
imagem disponível e válida -> image
imagem indisponível -> initials ou unknown
```

## Entidade: Estado de sobreposição da casca

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| `activeOverlay` | enum | `none`, `personal-menu` ou `mobile-navigation` | Apenas uma sobreposição estrutural pode estar ativa por vez. |
| `returnFocusTarget` | referência transitória | Presente ao abrir uma sobreposição | Permite devolver foco ao acionador após fechamento. |

### Transições de estado

```text
none -> personal-menu -> none
none -> mobile-navigation -> none
personal-menu -> mobile-navigation
mobile-navigation -> personal-menu
```

## Entidade: Preferências de apresentação

Esta feature consome o conjunto local existente de tema, idioma, escala de texto, espaçamento e tamanho de elementos. Não modifica seu formato, propriedade, persistência ou fonte de verdade.
