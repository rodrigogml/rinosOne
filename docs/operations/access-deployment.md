# Operação e Implantação do Acesso

Este documento orienta a preparação de uma instância que executa exclusivamente o acesso inicial de usuários. Valores concretos, domínios, destinatários e segredos pertencem ao arquivo `.env` do ambiente e nunca ao repositório.

## Configuração por ambiente

Copie `.env.example` para `.env` e gere uma chave própria com `php artisan key:generate`. O schema principal é `rinosone`; o prefixo `rinosone_` é reservado para tenants futuros e não deve resultar na criação antecipada de schemas.

| Grupo | Variáveis | Regra operacional |
| --- | --- | --- |
| Aplicação | `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Em produção, usar `APP_ENV=production`, `APP_DEBUG=false`, chave exclusiva e URL HTTPS pública. |
| Banco | `DB_*`, `RINOS_CORE_DATABASE`, `RINOS_TENANT_DATABASE_PREFIX`, `TENANT_RUNTIME_*`, `TENANT_PROVISIONING_*` | Usar credenciais separadas para runtime e provisionamento. Executar migrations globais antes de iniciar a aplicação; veja [provisionamento de tenants](tenant-provisioning.md). |
| Cookies e sessão | `SESSION_*`, `ACCESS_PERSISTENT_*` | Em produção, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` e `SESSION_SAME_SITE=lax`. A sessão não expira por inatividade quando o valor configurado é `0`; a persistência só é criada após escolha explícita do usuário. |
| Fila | `QUEUE_CONNECTION`, `DB_QUEUE_*` | Manter `database` e `DB_QUEUE_AFTER_COMMIT=true` para que mensagens só sejam entregues após a emissão estar persistida. |
| E-mail | `MAIL_*` | Usar `smtp` somente com servidor SMTP configurado. `APP_URL` determina a raiz dos links enviados; localhost é adequado somente ao teste local. |
| Captura de testes | `MAILPIT_API_URL` | Usada apenas pela integração automatizada para consultar o Mailpit; não configurar em produção. |
| Política de acesso | `ACCESS_*` | Alterar limites, prazo de emissão, retenção de logs e persistência apenas por configuração de ambiente. O modelo traz 10 minutos para emissões, espera de 3 minutos entre reenvios, 3 erros máximos por código e 30 dias para logs. |

> [!IMPORTANT]
> Não usar `MAIL_MAILER=log` em produção. Não versionar `.env`, chaves, senhas de banco, senha SMTP ou o destinatário de validação.

## Serviços de execução

A fila de banco processa o envio assíncrono de e-mail. Execute ao menos um worker supervisionado:

```sh
php artisan queue:work database --sleep=1 --tries=3 --max-time=3600
```

O scheduler exclui emissões vencidas no intervalo definido por `ACCESS_EXPIRED_CHALLENGE_CLEANUP_INTERVAL_MINUTES`. Em produção, agende uma execução por minuto do comando abaixo; o Laravel decide quando a tarefa deve efetivamente rodar:

```sh
php artisan schedule:run
```

Para teste local contínuo, pode-se usar:

```sh
php artisan schedule:work
```

Após modificar `.env` em uma implantação, recarregue a configuração e reinicie os processos supervisionados:

```sh
php artisan config:cache
php artisan queue:restart
```

## Recuperação operacional

| Situação | Ação segura |
| --- | --- |
| Worker de fila indisponível | Restaurar o worker. As mensagens pendentes permanecem na fila de banco; investigar jobs falhos sem copiar código, token ou link para tickets ou logs. |
| SMTP indisponível | Validar conectividade e credenciais somente no ambiente. Após restaurar o SMTP, reiniciar o worker; o job será reprocessado conforme a política da fila. |
| Sessão server-side indisponível | Restaurar a conexão com o banco. A pessoa com credencial persistente válida volta a receber uma sessão ao acessar a API; sem essa escolha, deverá entrar novamente. |
| Migrations globais pendentes | Colocar a aplicação em manutenção quando necessário, executar `php artisan migrate:global --force`, verificar o status e então liberar a aplicação. Migrations de tenant são responsabilidade do worker de provisionamento. |
| Limpeza de emissões atrasada | Restaurar o scheduler e executar `php artisan access:purge-expired-challenges`. A expiração lógica continua sendo aplicada na confirmação. |

## Checklist antes da liberação

- [ ] Domínio HTTPS público definido em `APP_URL` e certificado válido instalado.
- [ ] `APP_KEY` exclusiva do ambiente, `APP_DEBUG=false` e cookies marcados como seguros.
- [ ] Usuário MySQL restrito, schema `rinosone` existente e migrations executadas.
- [ ] SMTP, remetente e DNS de envio configurados fora do Git.
- [ ] Worker da fila e scheduler supervisionados e reiniciados automaticamente.
- [ ] Teste de cadastro realizado com um endereço autorizado, sem registrar código ou link em evidências.
- [ ] Suítes de qualidade executadas e sem falhas.
