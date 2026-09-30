# Arquitetura das Superfícies de Interação

**Criado**: 2026-09-22
**Última atualização**: 2026-09-30
**Status**: Aprovado
**Fontes**: briefing inicial, Constituição e plano da feature de acesso de usuário.

## Catálogo de Superfícies

| Surface ID | Tipo | Usuários | Plataformas e form factors | Cobertura de produto | Tecnologia, linguagem e runtime | Estratégia de entrega | Sistema de design | Módulo/repositório | Status da decisão |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | WEB | Visitante e usuário validado | Navegadores modernos em desktop, tablet e telefone | Cadastro, validação de e-mail, login, controle de sessões, casca autenticada, área de trabalho, fundação de tenants e indisponibilidade segura por incompatibilidade de schema | Vue 3, TypeScript e navegador moderno | SPA responsiva | Tokens CSS, componentes próprios, temas, preferências locais, i18n e área de trabalho autenticada | `resources/js`, `resources/css`, `resources/views` | Aprovado |
| SURF-WEB-MAINTENANCE | WEB | Administradores da Plataforma | Navegadores modernos em desktop, tablet e telefone | Central de manutenções, estados, histórico, auditoria e ações permitidas por rotina | Vue 3, TypeScript e navegador moderno | SPA responsiva | Sistema de design existente e componentes específicos de manutenção | `resources/js/maintenance`, `app`, `routes/api` | Planejado |
| SURF-WEB-ADMIN | WEB | Administradores autorizados de tenant e plataforma | Navegadores modernos em desktop, tablet e telefone | Administração contextual de participantes, papéis, grupos, acesso efetivo, auditoria, políticas e delegações | Vue 3, TypeScript e navegador moderno | SPA responsiva na Área de Trabalho | Sistema de design existente, i18n e componentes de formulário/tabela | `resources/js/authorization`, `app`, `routes/api` | Planejado |
| SURF-WEB-DRIVE | WEB | Usuário autenticado, administradores e membros autorizados de organização | Navegadores modernos em desktop, tablet e telefone | Ferramenta Rinos Drive única: catálogo pessoal/Work, compartilhados, painéis paralelos, transferência lógica, pastas, upload, download, lixeira e exportações privadas | Vue 3, TypeScript e navegador moderno | SPA responsiva dentro da Área de Trabalho | Sistema de design existente, i18n, árvore lazy, toolbar, seleção, drag-and-drop, diálogos e painéis reutilizáveis | `resources/js/drive`, `app`, `routes/api` | Planejado |
| SURF-WEB-PEOPLE | WEB | Usuários autorizados de organização | Navegadores modernos em desktop, tablet e telefone | Cadastro organizacional de Pessoas PF/PJ, endereços, contatos, contas, Pix, relacionamentos e ciclo de vida | Vue 3, TypeScript e navegador moderno | SPA responsiva dentro da Área de Trabalho | Sistema de design existente, i18n, formulários, tabelas, diálogos e confirmações destrutivas | `resources/js`, `resources/css`, `app`, `routes/api` | Planejado |
| SURF-WEB-SHARING | WEB | Responsáveis de workspace e administradores autorizados | Navegadores modernos em desktop, tablet e telefone | Gestão contextual de relações e compartilhamentos por recurso de workspace pessoal ou de tenant | Vue 3, TypeScript e navegador moderno | SPA responsiva invocada pelo Drive e pela administração | Sistema de design existente e componentes de compartilhamento | `resources/js/authorization`, `resources/js/drive`, `app`, `routes/api` | Planejado |
| SURF-FUTURE-CONSUMERS | API | Integrações e interfaces futuras autorizadas | A definir por capacidade | Contratos JSON versionados já definidos, sem interface ou consumidor entregue nesta fase | API JSON `/api/v1` | A definir | Contratos independentes da web | `app`, `routes/api` | Adiado |

## Decisões entre Superfícies

### Política de capacidade e paridade

A web responsiva é a única superfície humana entregue nesta fase. Consumidores futuros possuem somente a fronteira de API versionada necessária para a expansão, sem paridade implícita, interface ou cliente entregue.

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
| 2026-09-24 | SURF-WEB-ACCESS | O contexto de tenant complementa, mas não substitui, o workspace pessoal | Permite organizações distintas por aba sem esconder recursos pessoais nem compartilhar contexto | Plano da fundação de tenants |
| 2026-09-24 | SURF-WEB-ACCESS | A área autenticada evoluirá para uma Área de trabalho com superfícies efêmeras por aba | Permite produtividade desktop e adaptação móvel sem persistir telas ou contexto | Plano da Área de Trabalho |
| 2026-09-25 | SURF-WEB-ACCESS | A autorização projeta capabilities mínimas para a web, mas a decisão permanece no backend | Evita que estado visual se torne controle de acesso e permite revogação na próxima operação | Plano da Fundação de Autorização |
| 2026-09-26 | SURF-WEB-MAINTENANCE | A central de manutenções integra rotinas explicitamente e apresenta somente suas capacidades autorizadas | Preserva regras próprias de agenda, concorrência e execução, sem criar abstração genérica | Plano da Central de Manutenções |
| 2026-09-27 | SURF-WEB-MAINTENANCE | A atualização territorial do IBGE será uma integração de leitura no Hub, sem disparo manual | A rotina é automaticamente devida na primeira execução e mensalmente depois; o Hub a observa, mas não define suas regras | Plano da Fundação de Localidades |
| 2026-09-26 | SURF-WEB-ADMIN | A administração de segurança será uma superfície responsiva separada da área operacional | Evita misturar gestão de privilégios com uso cotidiano e preserva controles reforçados | SDDs de autorização |
| 2026-09-27 | SURF-WEB-DRIVE | Um navegador responsivo atende workspaces pessoal e organizacional por alvo tipado | Reutiliza a fundação única de arquivos sem permitir seleção livre de proprietário ou acervo | SDD Rinos Drive |
| 2026-09-30 | SURF-WEB-DRIVE | O Drive passa a ser ferramenta global de instância única, com catálogo multi-workspace e painéis paralelos | Reduz duplicação de entradas e permite transferência lógica segura entre workspaces, sem mover conteúdo físico | Plano Rinos Drive unificado |
| 2026-09-28 | SURF-WEB-PEOPLE | Um único cadastro responsivo de Pessoas entrega capacidade integral em desktop, tablet e telefone | Mantém paridade funcional aprovada sem duplicar clientes ou regras de negócio | SDD Cadastro de Pessoas |
| 2026-09-30 | SURF-WEB-ACCESS | A web exibe indisponibilidade controlada quando o schema global estiver incompatível e isola organizações em atualização | Impede operações contra dados incompatíveis sem indisponibilizar recursos pessoais ou outras organizações | SDD Compatibilidade de Schemas e Guarda Operacional |
| 2026-09-26 | SURF-WEB-SHARING | A gestão visual de compartilhamentos é uma superfície futura própria | Mantém a fundação por recurso independente do editor de relações | SDD de autorização por recurso |
| 2026-09-28 | SURF-WEB-ADMIN e SURF-WEB-SHARING | A experiência de acessos será contextual e única, com painel de compartilhamento reutilizável | Mantém as três esferas isoladas, simplifica a tarefa comum e conserva os controles avançados por divulgação progressiva | SDD Interface unificada de acessos e permissões |
