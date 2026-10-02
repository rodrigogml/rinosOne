# Plano de Implementação: Identidade Visual e Preferências de Interface

**Feature**: `visual-identity` | **Data**: 2026-09-23 | **Spec**: [spec.md](spec.md)

## Summary

Implementar uma fundação visual central para a web responsiva, com tokens em camadas, dez famílias cromáticas claro/escuro, Rubi Industrial como padrão, tipografia local, preferências de densidade, internacionalização e componentes compartilhados. A interface de acesso será reorganizada em telas compostas pelos novos elementos, sem alterar regras de autenticação, APIs ou persistência de domínio.

## Technical Context

**Linguagem/versão**: TypeScript 5.7, CSS e PHP 8.4 para o documento inicial da aplicação.
**Dependências principais**: Vue 3.5, Pinia 4, Tailwind CSS 4, Vite 7; adicionar Vue I18n 11 e pacotes variáveis locais de Inter e Space Grotesk.
**Armazenamento**: preferências visuais exclusivamente no armazenamento local do navegador; não há alteração no MySQL.
**Testes**: Vitest, Vue Test Utils e Playwright; testes atuais de backend permanecem como regressão do fluxo de acesso.
**Plataforma-alvo**: navegadores modernos em desktop, tablet e telefone.
**Tipo de projeto**: aplicação web monolítica modular com SPA.
**Metas de desempenho**: aplicar preferências antes da primeira pintura; não fazer solicitação de rede adicional para trocar tema, idioma ou densidade.
**Restrições**: WCAG 2.2 AA, preferência por redução de movimento, quatro idiomas iniciais, ativos originais imutáveis, nenhuma mudança nas regras de autenticação.
**Escala/escopo**: quatro telas existentes de acesso e uma fundação reutilizável para produtos futuros.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED — as quatro telas de acesso serão reorganizadas e exigem estados, navegação, responsividade e acessibilidade detalhados.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
| --- | --- | --- | --- | --- |
| SURF-WEB-ACCESS | FULL | Vue 3, TypeScript, Tailwind CSS e CSS custom properties no navegador | `resources/js`, `resources/css`, `resources/views` | Componentes compartilhados, tokens em camadas, i18n e preferências locais. |

## Constitution Check

*GATE: Deve passar antes do Phase 0. Rechecado após o Phase 1.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | A iniciativa se limita à fundação visual e às telas de acesso existentes; não introduz módulos de negócio. |
| II. Fronteira API e domínio independente da interface | PASS | Não cria nem altera regra de domínio ou contrato de acesso; a interface continua consumindo os contratos existentes. |
| III. Identidade e acesso seguros por padrão | PASS | Senhas, códigos, tokens e dados de jornada não são persistidos nas preferências nem inseridos em catálogos de tradução. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | A preferência é local ao navegador e não muda sessões, banco, segredos ou configurações por ambiente. |
| V. Mudanças verificáveis e documentação alinhada | PASS | O plano prevê testes unitários, integração visual e fluxos reais, além de artefatos SDD vinculados. |

## Arquitetura de Design System

### Tokens

Os arquivos de estilo serão organizados em quatro responsabilidades:

1. **Primitivos**: escalas brutas de cor, tamanho, peso, raio, sombra, duração e elevação; não podem ser consumidos diretamente por telas.
2. **Semânticos**: papéis independentes de tema, como superfície, texto, borda, foco, ação primária, informação, sucesso, aviso e perigo.
3. **Preferências calculadas**: multiplicadores raiz independentes para fonte, espaçamento e tamanho de componente, aplicados às dimensões derivadas.
4. **Componentes**: aliases para cartão, campo, botão, alerta e controle; podem consumir somente tokens semânticos e calculados.

As escalas iniciais são:

| Dimensão | Compacto | Padrão | Confortável/amplo |
| --- | ---: | ---: | ---: |
| Fonte | 0,875 | 1 | 1,125 |
| Espaçamento | 0,875 | 1 | 1,25 |
| Componentes | 0,9 | 1 | 1,125 |

Os multiplicadores alteram valores derivados, incluindo tamanhos de fonte, line-height, padding, gaps, dimensões mínimas de toque e ícones. Valores fixos de acessibilidade, como contraste, contorno de foco e área mínima de interação, permanecem protegidos de redução indevida.

### Temas e paleta

O elemento raiz recebe atributos de tema e preferência de densidade. Cada família cromática define em tokens primitivos as variantes claro e escuro para canvas, superfície, texto, texto secundário, ação, hover e foco; aliases semânticos consomem somente a família ativa. Rubi Industrial resolve a ausência de atributo de família com `L8` no tema claro e `D8` no escuro. As demais famílias ficam endereçáveis por `data-palette` para uso futuro no perfil, sem campo local, API ou seletor nesta fase. Grafite e prata sustentam o tema escuro, cujo canvas recebe somente uma trama neutra de fibra de carbono, sem gradientes da cor de destaque.

Cada cor semântica terá variante apropriada para conteúdo, contorno e foco em ambos os temas. O canvas da aplicação é deliberadamente neutro e independente da família cromática: branco absoluto no tema claro e preto absoluto no tema escuro. A validação deverá confirmar contraste de 4,5:1 para texto normal, 3:1 para texto grande e contornos de foco perceptíveis sobre a superfície adjacente.

### Movimento e responsividade

Tokens de duração definem 120 ms para feedback de controle e 180 ms para abertura, fechamento ou mudança de superfície, ambos com curva de desaceleração padrão. Nenhuma transição é necessária para compreender conteúdo ou concluir uma ação; a preferência do sistema para redução de movimento reduz a duração a zero.

Os breakpoints de layout são centralizados: telefone até 639 px, tablet de 640 a 1023 px e desktop a partir de 1024 px. Componentes preservam uma coluna quando a largura não suportar duas regiões sem reduzir alvo de toque, e nenhum breakpoint pode criar rolagem horizontal para conteúdo de acesso.

### Preferências e inicialização

Um módulo de preferências será a fonte de verdade para `theme`, `locale`, `fontScale`, `spacingScale` e `componentScale`. Ele valida o formato, aplica padrões e expõe leitura e atualização atômicas. Um script mínimo no documento aplica os atributos raiz e `lang` antes da montagem da SPA; o módulo reativo confirma e atualiza o estado depois da montagem.

Os valores são gravados apenas quando pertencem ao conjunto permitido. Dados de formulário, senha, código, token, e-mail e estado de autenticação não serão gravados no mesmo armazenamento. Quando no futuro houver perfil, uma integração posterior poderá sincronizar preferências sem alterar o formato local atual.

### Internacionalização

O módulo de i18n mantém catálogos tipados de `pt-BR`, `en`, `es` e `fr` em arquivos independentes, mas com a mesma estrutura de chaves. `pt-BR` é o valor padrão e fallback explícito. O seletor de idioma altera o idioma global, o atributo `lang` do documento e os rótulos acessíveis, sem navegação forçada ou descarte de entrada segura.

O seletor fornece bandeira como apoio visual, nome do idioma como texto e indicação textual do idioma atual. Nenhum texto de produção permanecerá diretamente codificado nos componentes de acesso. Textos de e-mail não são alterados por esta feature; sua localização é uma capacidade futura, salvo quando uma alteração for aprovada separadamente.

### Tipografia, ícones e ativos

As famílias variáveis serão empacotadas localmente: Inter para leitura e controles, Space Grotesk para títulos e destaques. A escala tipográfica é derivada de tokens e multiplicadores; não há tamanho local solto em tela.

O logotipo paisagem e o ícone serão copiados de `etc/ID Visual/` para ativos públicos derivados. O logotipo da entrada terá largura de 80% do cartão, máximo controlado por token e altura automática. O ícone será convertido em variantes adequadas para favicon e manifesto PWA; a cópia de origem não será alterada. O acesso às preferências usará ícone de ajustes visuais e abrirá um popup com as quatro linhas de escolhas; cada opção terá nome acessível e não dependerá somente da forma visual.

### Componentes compartilhados

| Componente | Responsabilidade | Uso inicial |
| --- | --- | --- |
| `AppShell` | Fundo, centralização, tema e região principal | Todas as telas de acesso |
| `BrandMark` | Logotipo paisagem ou ícone, com variantes de tamanho | Entrada, criação e áreas futuras |
| `UiCard` | Superfície elevada e espaçamento interno padronizado | Cartões de acesso e agrupamentos |
| `UiField` | Rótulo, controle, ajuda, erro e estado acessível | E-mail, senha, código e nome |
| `UiButton` | Ações textuais compartilhadas; seu contrato visual canônico está no [guia vivo de Botões](/dev) | Ações principais, secundárias e destrutivas |
| `UiAlert` | Feedback de sucesso, erro, aviso e informação | Todos os fluxos de acesso |
| `IconButton` | Ação somente por ícone com rótulo acessível | Tema e controles futuros |
| `VisualPreferencesPopover` | Tema e três escalas de densidade em quatro grupos de escolhas | Entrada, criação e áreas futuras |
| `LanguageSelector` | Idioma, bandeira, nome e menu acessível | Entrada, criação e áreas futuras |

As telas apenas compõem estes componentes. O padrão visual e os exemplos executáveis de botões são mantidos exclusivamente no [guia vivo de Botões](/dev), disponível em homologação. Um novo caso de uso deve ampliar um componente existente ou introduzir componente compartilhado com contrato próprio; não haverá estilização exclusiva em tela sem justificativa documentada.

## Project Structure

### Documentation (this feature)

```text
docs/specs/visual-identity/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── interface-spec.md       # etapa 6
```

### Source Code (repository root)

```text
resources/
├── css/
│   ├── app.css
│   └── design-system/      # tokens, temas, base e componentes
├── js/
│   ├── App.vue
│   ├── app.ts
│   ├── design-system/      # componentes e ícones compartilhados
│   ├── i18n/               # configuração e catálogos por idioma
│   └── preferences/        # estado, validação e persistência local
└── views/
    └── application.blade.php
public/
├── assets/brand/            # cópias derivadas do logotipo e ícone
├── favicon.ico
└── manifest.webmanifest
tests/
├── js/                      # unidades e componentes Vue
└── e2e/                     # fluxos web reais
```

**Structure Decision**: o design system, i18n e preferências residem na interface web, mantendo regras de acesso no domínio e sem criar uma biblioteca ou serviço separado antes de haver outro consumidor aprovado.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Preferência no navegador | camelCase | enum e versão ao carregar/gravar | módulo `resources/js/preferences/` |
| Catálogos de idioma | chaves aninhadas em camelCase | tipo derivado do catálogo `pt-BR` | módulo `resources/js/i18n/` |
| Atributos da raiz do documento | kebab-case | conjunto fechado de valores | módulo de preferências e estilos raiz |
| API de autenticação existente | camelCase | contratos e requests atuais | `docs/specs/user-auth/contracts/auth-api.md` |

**Mapper layer (browser <-> estado reativo)**: o módulo de preferências é o único responsável por validar, carregar e gravar a preferência local; componentes consomem sua interface pública.

**Validação de schema**: preferências e idiomas serão validados na leitura e na escrita no frontend. Não há payload ou alteração de schema de API nesta feature.

## Complexity Tracking

Nenhuma violação da Constituição foi introduzida.
