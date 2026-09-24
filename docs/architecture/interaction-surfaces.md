# Arquitetura das Superfícies de Interação

**Criado**: 2026-09-22
**Última atualização**: 2026-09-24
**Status**: Aprovado
**Fontes**: briefing inicial, Constituição e plano da feature de acesso de usuário.

## Catálogo de Superfícies

| Surface ID | Tipo | Usuários | Plataformas e form factors | Cobertura de produto | Tecnologia, linguagem e runtime | Estratégia de entrega | Sistema de design | Módulo/repositório | Status da decisão |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Visitante e usuário validado | Navegadores modernos em desktop, tablet e telefone | Cadastro, validação de e-mail, login, controle de sessões e casca autenticada global | Vue 3, TypeScript e navegador moderno | SPA responsiva | Tokens CSS, componentes próprios, temas, preferências locais, i18n e casca autenticada | `resources/js`, `resources/css`, `resources/views` | Aprovado |

## Decisões entre Superfícies

### Política de capacidade e paridade

Somente a web responsiva possui cobertura nesta fase. Consumidores futuros permanecem adiados e não possuem paridade implícita.

### Domínio e contratos compartilhados

O domínio de acesso é a autoridade sobre elegibilidade da conta, emissões temporárias e sessões. A web consome contratos JSON versionados e não replica regras de validação, expiração ou autorização.

### Estratégia de código compartilhado

Tipos de contrato, cliente HTTP e componentes de acesso pertencem à interface web. O domínio, a persistência e as regras de segurança permanecem no backend. Não há código compartilhado com superfícies futuras nesta fase.

### Acessibilidade e entradas

Todos os fluxos de acesso devem funcionar por teclado e toque, apresentar erros e sucesso de forma perceptível sem depender apenas de cor e manter foco previsível após envio, validação, login ou bloqueio temporário.

### Localização e conteúdo

O idioma inicial é português do Brasil. A web oferece também inglês, espanhol e francês por seletor reutilizável e mantém a escolha no navegador. Mensagens de e-mail e de acesso pertencem ao produto e devem evitar textos que revelem se uma conta existe.

### Identidade e preferências visuais

A web adota dez famílias cromáticas claro/escuro em tokens, com Rubi Industrial como padrão e fundo escuro grafite de trama carbono neutra. Tema, idioma e três escalas independentes para fonte, espaçamento e tamanho de componentes continuam locais; a seleção da família será persistida pelo perfil em fase futura e não contém dados de autenticação ou formulário.

## Histórico de Decisões

| Data | Surface ID | Decisão | Racional | Fonte |
| --- | --- | --- | --- | --- |
| 2026-09-22 | SURF-WEB-ACCESS | Uma única web responsiva cobre o acesso inicial | Não há outra superfície aprovada; a API preserva a expansão futura | Briefing inicial |
| 2026-09-23 | SURF-WEB-ACCESS | A fundação visual inclui temas, i18n e preferências locais | Mantém a experiência consistente e reutilizável sem expandir a capacidade de negócio | Plano de identidade visual |
| 2026-09-24 | SURF-WEB-ACCESS | A área autenticada adota uma casca global com barra, área pessoal e painel móvel | Mantém pontos de orientação estáveis e prepara a futura navegação de módulos sem antecipar produtos | Plano da casca autenticada |
