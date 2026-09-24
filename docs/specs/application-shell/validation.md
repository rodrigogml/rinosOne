# Validação — Casca da Aplicação Autenticada

**Data:** 2026-09-24  
**Escopo:** `application-shell` — INT-WEB-SHELL-001, INT-WEB-SHELL-002 e INT-WEB-SHELL-003.

## Resultado

A casca autenticada foi validada com sucesso nas larguras de telefone (375 px), tablet (768 px) e desktop (1440 px). A barra usa a marca paisagem compacta em todos os formatos, com maior hierarquia que o avatar e sem moldura visual no acionador móvel; mantém menu pessoal e painel lateral sem introduzir destinos de produto fictícios.

## Evidências automatizadas

| Verificação | Comando | Resultado |
| --- | --- | --- |
| Testes de componentes e interface | `npm test` | 11 arquivos e 50 testes aprovados. |
| Verificação de tipos Vue/TypeScript | `npm run type-check` | Aprovada, sem diagnósticos. |
| Testes de backend | `php artisan test` | 69 testes e 276 asserções aprovados; 2 integrações de e-mail ignoradas por configuração ausente. |
| Jornada web ponta a ponta | `PLAYWRIGHT_BROWSER_CHANNEL=chrome npm run test:e2e` | 13 testes aprovados. |
| Build de produção | `npm run build` | Aprovado. |

Os cenários ponta a ponta verificaram: autenticação existente, avatar da sessão, menu pessoal, preferências visuais, troca de idioma, logout, teclado, painel móvel, ausência de links fictícios, telefone/tablet/desktop, redução de movimento, alvos mínimos e ausência de rolagem horizontal.

## Inspeção visual

Foram inspecionadas capturas produzidas pela suíte ponta a ponta nos estados:

- área autenticada escura em desktop, com menu pessoal aberto;
- área autenticada escura e confortável em tablet;
- área de acesso escura e confortável em telefone;
- área de acesso clara e compacta em desktop.

O limite semântico da marca na barra foi ajustado para `--size-brand-top-bar-max`, preservando a proporção visual esperada sem impactar a marca ampla da área de acesso. As capturas também confirmaram o contraste, o foco navegável, a disposição dos controles e a inexistência de overflow horizontal nos três formatos.

## Rastreabilidade de fechamento

| Interação | Requisitos principais | Evidência de implementação e validação |
| --- | --- | --- |
| INT-WEB-SHELL-001 | FR-001–006, FR-013–015 | `AuthenticatedFrame`, `ApplicationTopBar`, `UserAvatar`; testes Vitest de avatar e barra; jornada Playwright autenticada nos três formatos. |
| INT-WEB-SHELL-002 | FR-005, FR-007–010, FR-013–015 | `UserMenu`, preferências visuais e seletor de idioma reutilizados; testes de foco, Escape, idioma e saída; jornada Playwright autenticada. |
| INT-WEB-SHELL-003 | FR-011–015 | `MobileNavigationDrawer`; testes de componente para foco, Escape e fundo; jornada Playwright no telefone sem links de produto. |

Os papéis, nomes acessíveis, foco inicial, retorno de foco e Escape são cobertos por testes de componente e ponta a ponta. Os wireframes `INT-WEB-SHELL-001` a `003` permanecem aderentes: não houve divergência material que exigisse alteração.

## Limitações de ambiente

> [!NOTE]
> As duas integrações SMTP/Mailpit permanecem ignoradas quando `MAILPIT_API_URL` não está definido. Isso não representa falha da casca autenticada; a suíte informa explicitamente a condição.

> [!NOTE]
> O navegador empacotado do Playwright não estava instalado e seu download excedeu o tempo de rede. A suíte ponta a ponta foi executada com sucesso usando o canal Chrome já instalado na máquina, sem alteração permanente na configuração do projeto.
