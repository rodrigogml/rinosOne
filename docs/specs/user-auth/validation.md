# Evidências de Validação — Acesso de Usuário

## Execução automatizada local

Em 23 de setembro de 2026, as validações locais disponíveis concluíram sem falhas:

| Verificação | Resultado |
| --- | --- |
| `php artisan test` | 64 testes aprovados, 216 asserções; 2 testes SMTP ignorados sem Mailpit local. |
| `php vendor/bin/pint --test` | Formatação aprovada. |
| `npm run type-check` | Tipagem TypeScript aprovada. |
| `npm run test` | 14 testes de componente aprovados. |
| `npm run build` | Bundle de produção gerado. |
| `PLAYWRIGHT_BROWSER_CHANNEL=chrome` e `PHP_BINARY=<PHP 8.4> npm run test:e2e` | 9 cenários de navegador aprovados localmente. |

Os cenários E2E versionados exercitam cadastro, confirmação por código e link, login por senha e sem senha, persistência, restauração de sessão, definição de senha, encerramento atual, invalidação de outras sessões e navegação por teclado. Localmente, eles podem usar um canal de navegador já instalado; no CI, a workflow instala o Chromium do Playwright.

A cobertura E2E inclui simulação de telefone (375 × 667), tablet (768 × 1024) e desktop (1440 × 900), verificando controles visíveis, área mínima de toque e ausência de rolagem horizontal quando o navegador de teste estiver instalado.

## Integração SMTP automatizada

Os testes `MailpitEmailRoundtripTest` exercitam cadastro e acesso sem senha passando pela fila de banco e SMTP. Eles capturam a mensagem no Mailpit, validam destinatário, assunto, código numérico e rota do link, e usam o código capturado para concluir cada jornada. A workflow inicia Mailpit e executa esses testes; a execução local aguarda um runtime Docker, indisponível nesta máquina durante esta validação.

## Banco e configuração local

O status de migrations confirma as quatro migrations aplicadas à instância configurada, incluindo a separação dos hashes de código e link. A consulta foi feita sem exibir host, credenciais ou outros valores reais de ambiente. O scheduler registra a limpeza de emissões vencidas e o modelo de ambiente contém as configurações de banco, fila, e-mail, cookies e política de acesso.

## Roundtrip de e-mail no ambiente de testes

Em 23 de setembro de 2026, um cadastro foi iniciado pela interface web local com um endereço de teste autorizado. A API retornou a jornada de confirmação, o registro pendente e a emissão de validação foram persistidos no MySQL e um job foi incluído na fila de banco. O worker processou uma única mensagem `EmailVerificationMessage` com sucesso; a fila voltou a zero jobs pendentes.

A pessoa responsável pelo endereço de teste confirmou manualmente o recebimento da mensagem, contendo o código numérico de seis dígitos e o link. A confirmação pelo código criou a sessão autenticada, consumiu a emissão e validou a conta. Em uma nova emissão de acesso sem senha, três códigos numéricos incorretos foram submetidos pela interface; a terceira tentativa excluiu a emissão. A consulta posterior confirmou zero emissões ativas e zero jobs pendentes, portanto o link daquela emissão também ficou inválido. Códigos, links e destinatários não foram registrados nesta evidência.

## Auditoria de dados sensíveis

As respostas de autenticação e sessão retornam somente o identificador público, o nome de exibição e o estado de senha necessário à interface. A auditoria de código não encontrou retorno de e-mail completo nesses contratos. O canal de segurança registra apenas o tipo de evento e, quando aplicável, o identificador público da conta; senhas, códigos, links e tokens não são registrados.

## Validações externas pendentes

- A inspeção manual em dispositivos físicos e a liberação de produção requerem domínio HTTPS, certificado, processo supervisor e ambiente de implantação definidos pela infraestrutura.

> [!NOTE]
> Os itens pendentes são pré-requisitos externos; a implementação e as validações automatizadas locais permanecem concluídas.
