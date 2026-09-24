# Validação: Identidade Visual e Preferências de Interface

**Data:** 2026-09-23

## Automação

| Verificação | Resultado | Evidência |
| --- | --- | --- |
| Testes de interface | Aprovado | `npm test` — 33 testes Vitest. |
| Tipos | Aprovado | `npm run type-check`. |
| Build | Aprovado | `npm run build`. |
| Testes PHP | Aprovado | `php artisan test` — 69 aprovados; 2 integrações Mailpit ignoradas sem ambiente dedicado. |
| Formatação | Aprovado | `php vendor/bin/pint --test`. |
| Tokens | Aprovado | `DesignSystemStylesTest` valida 20 ações claro/escuro, Rubi Industrial padrão, contraste e ausência de cores brutas fora da camada primitiva. |
| Ponta a ponta | Aprovado | `npm run test:e2e` — 10 cenários Playwright aprovados no Chrome local. |

## Cobertura consolidada

- Preferências locais normalizam valores inválidos e não persistem dados de acesso.
- Catálogos de português, inglês, espanhol e francês preservam paridade de chaves e fallback.
- Componentes compartilhados cobrem foco, diálogo, controles de apresentação e acessibilidade básica.
- Fluxos de entrada, cadastro, confirmação, sessão e ações destrutivas possuem testes de integração de interface.
- O catálogo de paletas não é persistido nem exposto na interface atual; Rubi Industrial é a resolução sem preferência de perfil.

## Validação ponta a ponta e responsiva

- Os contratos públicos de senha, acesso sem senha, cadastro, confirmações por código e link e consulta de sessão foram exercitados com os métodos, caminhos, payloads e respostas definidos em `user-auth/contracts/auth-api.md`.
- A alteração de tema, densidade e idioma foi verificada durante o cadastro: mantém a rota e os valores seguros de nome e e-mail, atualiza o idioma do documento e não grava e-mail nem senha nas preferências locais.
- A entrada foi capturada automaticamente em telefone (375 × 667), tablet (768 × 1024) e desktop (1440 × 900), nos extremos escuro/confortável e claro/compacto. Não houve rolagem horizontal e a área mínima de toque permaneceu em pelo menos 44 px.
- A inspeção identificou que, no telefone com as três escalas amplas, a parte inferior do popup poderia sair da viewport. O componente foi corrigido para usar painel fixo e rolável em telefone; a repetição automatizada dos três formatos foi aprovada.
- Navegação por teclado confirmou abertura, Escape e retorno de foco no diálogo de invalidação e no popup de preferências. A emulação de redução de movimento confirmou duração de transição `0s`; o manifesto responde com aplicação instalável independente.

> [!NOTE]
> O binário gerenciado do Chromium não pôde ser baixado por timeout de rede. A mesma suíte foi executada com o Chrome local já instalado, por meio de `PLAYWRIGHT_BROWSER_CHANNEL=chrome`.
