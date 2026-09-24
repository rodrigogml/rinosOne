# Plano de Implementação: Casca da Aplicação Autenticada

**Feature**: `application-shell` | **Data**: 2026-09-24 | **Spec**: [spec.md](spec.md)

## Summary

Evoluir a área autenticada para uma casca global e reutilizável, com barra superior permanente, avatar derivado do nome, menu pessoal e painel lateral móvel. A composição preserva a sessão, o fluxo de saída, as preferências visuais e o seletor de idioma existentes; não cria produtos, módulos, perfil, imagem de usuário, persistência ou API adicional.

## Technical Context

**Linguagem/versão**: TypeScript 5.7, CSS e PHP 8.5.  
**Dependências principais**: Vue 3.5, Pinia 4, Vue I18n 11, Vite 7, Laravel 12 e Axios.  
**Armazenamento**: nenhum novo armazenamento; a feature usa a sessão existente e consome preferências locais já aprovadas.  
**Testes**: Vitest, Vue Test Utils, Playwright e suíte de backend existente para regressão de sessão.  
**Plataforma-alvo**: navegadores modernos em desktop, tablet e telefone.  
**Tipo de projeto**: monólito modular Laravel com SPA responsiva.  
**Metas de desempenho**: nenhuma chamada adicional de rede para abrir ou fechar barra, avatar, menu pessoal, painel móvel, preferências ou idioma; preservar a consulta de sessão já existente.  
**Restrições**: WCAG 2.2 AA, quatro idiomas, tokens visuais e escalas existentes, redução de movimento, nenhuma função de produto, módulo ou perfil.  
**Escala/escopo**: uma única área autenticada atual e uma estrutura reutilizável para áreas autenticadas futuras.

## Arquitetura da Solução

### Responsabilidades

| Unidade | Responsabilidade | Limite explícito |
| --- | --- | --- |
| Aplicação raiz | Continua sendo a fonte de verdade da sessão, do carregamento e do encerramento de sessão. | Não controla detalhes visuais de menus. |
| Casca autenticada | Organiza barra, conteúdo principal, menu pessoal e painel móvel; recebe identidade e emite intenções. | Não chama diretamente endpoints de sessão. |
| Barra superior | Apresenta marca e acionadores globais responsivos. | Não contém navegação de produto nesta fase. |
| Avatar de usuário | Produz apresentação circular de imagem futura ou fallback derivado do nome. | Não persiste iniciais nem carrega arquivo. |
| Menu pessoal | Agrupa destino reservado, preferências, idioma e intenção de saída. | Não implementa perfil nem lógica de saída. |
| Painel móvel | Controla a estrutura de navegação em tela estreita e seu ciclo de foco. | Não fornece links de módulos antes de aprovados. |

### Fluxo de Estado

1. A aplicação carrega a sessão existente e entrega o nome de exibição à casca autenticada.
2. A casca apresenta a barra e o conteúdo atual; em desktop a marca é identidade estática, e em tela estreita ela aciona a navegação lateral.
3. Avatar e acionador móvel alternam um estado estrutural exclusivo; abrir um fecha o outro.
4. O menu pessoal delega preferências visuais e idioma aos componentes existentes e emite saída para a aplicação raiz.
5. A aplicação raiz executa a saída existente, limpa a sessão visual e retorna ao fluxo público.

### Componentes e Estilos Compartilhados

| Componente ou recurso | Alteração planejada | Reuso futuro |
| --- | --- | --- |
| `AuthenticatedFrame` | Passa de moldura pontual de segurança a composição global autenticada. | Todas as áreas autenticadas. |
| `ApplicationTopBar` | Marca, layout de barra e acionadores desktop/móvel. | Todas as áreas autenticadas. |
| `UserAvatar` | Algoritmo puro de apresentação de avatar e imagem futura opcional. | Cabeçalhos, listas, comentários e menus futuros. |
| `UserMenu` | Destino reservado, utilitários e evento de saída. | Barra e outras superfícies com contexto pessoal. |
| `MobileNavigationDrawer` | Painel lateral, foco, fechamento e espaço para destinos futuros. | Navegação de produtos quando autorizada. |
| `BrandMark` | Acrescenta a variante de marca proporcional adequada à barra, se necessária. | Todas as superfícies que exigirem identidade reduzida. |
| Catálogos de idioma | Incluem os rótulos da casca nos quatro idiomas. | Toda interface futura. |
| Tokens e estilos de componentes | Acrescentam apenas aliases semânticos e classes compartilhadas de barra, avatar, menus e painel. | Todas as áreas autenticadas. |

### Acessibilidade e Sobreposições

- Aberturas possuem nome acessível, estado expandido e relação com o conteúdo aberto.
- Menu pessoal e painel móvel devolvem foco ao acionador ao fechar; Escape fecha a sobreposição ativa.
- O painel móvel usa camada de fundo e impede interação acidental com o conteúdo enquanto aberto, sem prender o conteúdo fora da tela após o fechamento.
- A área pessoal permanece alcançável por teclado; ícones nunca são a única identificação de ação.
- Preferências visuais e idioma mantêm seus contratos acessíveis existentes dentro do menu pessoal.
- Os estilos usam tokens e respeitam escalas, contraste e redução de movimento já definidos.

## Arquitetura das Superfícies de Interação

**Catálogo de superfícies**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)  
**Interface Design Applicability**: REQUIRED — a feature introduz uma barra global, dois menus e comportamento responsivo que precisam de estados, ordem de foco, wireframes e regras adaptativas detalhadas.

| Surface ID | Cobertura | Decisão tecnológica | Módulo | Notas |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | FULL | Vue, TypeScript e CSS tokens no navegador | `resources/js`, `resources/css` | Casca autenticada incorporada à web responsiva existente. |

## Constitution Check

*GATE: Deve passar antes do Phase 0. Rechecado após o Phase 1.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | Introduz apenas a estrutura global e destinos reservados; não cria módulos, perfil ou navegação fictícia. |
| II. Fronteira API e domínio independente da interface | PASS | Reutiliza leitura e saída de sessão existentes; a casca não recebe regras de domínio. |
| III. Identidade e acesso seguros por padrão | PASS | Não armazena nem expõe credenciais, tokens ou dados adicionais; saída permanece no fluxo aprovado. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Não cria dado persistido, configuração por ambiente ou segredo. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Prevê testes unitários, fluxos reais de sessão e interação responsiva, vinculados aos artefatos SDD. |

## Project Structure

```text
docs/
├── architecture/
│   └── interaction-surfaces.md
└── specs/application-shell/
    ├── spec.md
    ├── research.md
    ├── data-model.md
    ├── quickstart.md
    ├── plan.md
    └── interface-spec.md            # etapa seguinte
resources/
├── css/design-system/
│   └── components.css               # estilos compartilhados da casca
└── js/
    ├── App.vue                      # sessão e intenção de saída
    ├── i18n/                        # novos rótulos nos quatro idiomas
    └── design-system/
        ├── AuthenticatedFrame.vue
        ├── ApplicationTopBar.vue
        ├── UserAvatar.vue
        ├── UserMenu.vue
        └── MobileNavigationDrawer.vue
tests/
├── js/design-system/                # unidades de avatar e menus
└── e2e/application.spec.ts          # jornadas autenticadas reais e responsivas
```

**Decisão de estrutura**: a casca e seus elementos ficam junto do design system da interface atual. Não há pacote de módulos nem biblioteca externa até que exista outro consumidor autorizado.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Resposta de sessão | camelCase | contrato e cliente de sessão existentes | `docs/specs/user-auth/contracts/auth-api.md` |
| Dados de apresentação de avatar | camelCase | normalização pura no componente ou função de apresentação | contrato do componente compartilhado |
| Eventos entre casca e aplicação | kebab-case | tipos de emissão e listeners da interface | componentes da casca autenticada |
| Catálogos de idioma | chaves aninhadas em camelCase | tipo derivado do catálogo base | `resources/js/i18n/` |
| Atributos visuais da raiz | kebab-case | conjunto fechado de preferências existente | módulo de preferências visuais |

**Mapper layer (sessão -> apresentação)**: a aplicação raiz fornece o nome de exibição já recebido da sessão; o avatar o normaliza exclusivamente para apresentação. Nenhuma transformação retorna ao backend.

**Validação de schema**: o payload da sessão segue o contrato de acesso existente; a feature valida apenas entradas internas fechadas de apresentação e estado de sobreposição. Não há request ou response novo.

## Cenários de Validação

Os cenários detalhados estão em [quickstart.md](quickstart.md): fallback de avatar, menu pessoal, saída, painel móvel, roundtrip da sessão e acessibilidade em apresentações extremas.

## Complexity Tracking

Nenhuma violação da Constituição foi introduzida.
