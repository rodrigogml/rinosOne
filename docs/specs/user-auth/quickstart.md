# Quickstart de Validação: Acesso de Usuário

## Configuração local

Copie `.env.example` para `.env` e informe os valores concretos do ambiente. O arquivo versionado contém somente placeholders; credenciais de banco e SMTP pertencem exclusivamente ao `.env` ignorado pelo Git.

Para o envio SMTP com TLS implícito, informe `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, host, porta, usuário, senha e remetente. `APP_URL` é a URL raiz usada na geração dos links de e-mail: use `http://localhost` neste ambiente e substitua-a pelo domínio público na implantação.

As configurações `ACCESS_*` agrupam os valores ajustáveis da política de autenticação. Os valores `0` para inatividade de sessão e duração de autenticação persistente representam ausência de expiração; a aplicação dessa política será introduzida nas tarefas de domínio e autenticação.

Eventos de segurança usam um canal diário separado. `ACCESS_SECURITY_LOG_RETENTION_DAYS` define sua retenção, com padrão de 30 dias; o registrador aceita somente tipos de evento e identificador interno de usuário, impedindo o registro de e-mail, senha, código, link ou token.

## Automação de qualidade

Em push e pull request, a workflow `Quality` prepara uma base SQLite efêmera, executa formatação PHP, testes de backend e frontend, verificação de tipos, build, auditorias das dependências de produção e testes de navegador com Chromium. Ela inicia Mailpit para validar a entrega SMTP, conteúdo, código e link das mensagens sem encaminhá-las a uma caixa postal externa.

Para executar a mesma integração localmente, inicie o Mailpit e informe as variáveis do ambiente de teste:

```sh
docker compose -f compose.testing.yml up -d mailpit
MAILPIT_API_URL=http://127.0.0.1:8025 MAIL_HOST=127.0.0.1 MAIL_PORT=1025 php artisan test --filter=MailpitEmailRoundtripTest
```

No PowerShell, use a sintaxe equivalente antes do comando: `$env:MAILPIT_API_URL='http://127.0.0.1:8025'; $env:MAIL_HOST='127.0.0.1'; $env:MAIL_PORT='1025'; php artisan test --filter=MailpitEmailRoundtripTest`.

Para executar E2E local usando um Chrome já instalado, informe também o PHP 8.4: `$env:PLAYWRIGHT_BROWSER_CHANNEL='chrome'; $env:PHP_BINARY='C:\caminho\para\php.exe'; npm run test:e2e`.

> [!IMPORTANT]
> Mailpit é exclusivo de desenvolvimento e CI. A configuração SMTP real de homologação ou produção continua no `.env` do ambiente.

As evidências mais recentes das validações locais estão em [validation.md](validation.md). Para operação e implantação, consulte [access-deployment.md](../../operations/access-deployment.md).

## Cenário 1: Cadastro e ativação por código

1. Informar um e-mail novo no cadastro.
2. Abrir a mensagem recebida e copiar o código de validação.
3. Informar o código e o nome de exibição na web.
4. **Esperado**: a conta fica validada, o código e o link da mesma emissão deixam de funcionar e o usuário pode acessar as opções de autenticação aprovadas.

## Cenário 2: Acesso sem senha por link

1. Usar uma conta validada sem senha definida.
2. Solicitar acesso sem senha.
3. Abrir o link mágico recebido em uma nova aba.
4. **Esperado**: a nova aba inicia uma sessão autenticada, e o código da mesma mensagem é recusado se for usado depois.

## Cenário 3: Senha e controle de sessões

1. Em uma sessão autenticada, definir uma senha com pelo menos 6 caracteres e 2 categorias exigidas.
2. Iniciar uma segunda sessão por e-mail e senha em outro navegador.
3. Na primeira sessão, solicitar a invalidação das demais sessões.
4. **Esperado**: a primeira sessão e sua autenticação persistente, quando houver, permanecem ativas; a segunda deixa de conceder acesso e não pode reconstruir a sessão automaticamente.

## Cenário 4: Manter-me conectado e reconstruir sessão

1. Solicitar acesso por e-mail com “Manter-me conectado” selecionado e concluir por código ou link.
2. Fechar e reabrir o navegador.
3. Simular a perda da sessão server-side e retornar à web.
4. **Esperado**: a autenticação persistente restaura uma sessão válida sem solicitar novas credenciais.

## Cenário 5: Erro e proteção contra abuso

1. Solicitar repetidamente um código de acesso além do limite configurado.
2. Tentar confirmar uma emissão já usada, substituída ou com mais de 10 minutos.
3. **Esperado**: o sistema aplica bloqueio temporário ou recusa o acesso sem revelar se o e-mail possui conta.

## Cenário 6: Roundtrip End-to-End

1. Iniciar o backend e a interface locais com a configuração de e-mail de desenvolvimento habilitada.
2. Enviar uma solicitação real de cadastro ao endpoint de registro e obter uma emissão de validação pelo canal de desenvolvimento autorizado.
3. Confirmar a emissão por código e capturar a resposta real de conclusão.
4. Comparar os campos, tipos e nomes da resposta com `contracts/auth-api.md` e com o tipo consumido pela interface.
5. Concluir a jornada pela web usando o mesmo backend, sem fixture ou mock.
6. **Esperado**: contrato, resposta real e estado observável na web não apresentam divergências.

## Cenário 7: Roundtrip de interação humana

1. Em desktop, tablet e telefone, iniciar o cadastro pela interface web.
2. Concluir a confirmação por código e repetir a jornada por link em outra aba.
3. Navegar pelos campos e ações somente por teclado e repetir a ação principal por toque.
4. **Esperado**: os estados de envio, sucesso, erro, bloqueio temporário e foco são compreensíveis e operáveis em todos os form factors suportados.
