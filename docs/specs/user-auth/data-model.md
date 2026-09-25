# Modelo de Dados: Acesso de Usuário

## Distribuição Física

Todas as entidades desta feature pertencem ao schema principal `rinosone`. Nenhuma tabela de tenant, referência entre schemas ou migration de tenant é criada nesta fase. A convenção reservada para evolução futura está em [database-topology.md](../../architecture/database-topology.md).

## Entity: User

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | chave primária | Identificador técnico da conta. |
| email | string | obrigatório, único, normalizado | Identidade de acesso. |
| displayName | string | obrigatório para acesso; ausente durante ativação | Nome informado após confirmar o e-mail. |
| passwordHash | string | opcional | Nunca armazena senha em texto. |
| emailVerifiedAt | timestamp | opcional | Define que o e-mail foi confirmado. |
| createdAt | timestamp | obrigatório | Registro de criação. |
| updatedAt | timestamp | obrigatório | Registro de alteração. |

### Relationships

- User 1:N AuthenticationChallenge.
- User 1:N Session.
- User 1:N PersistentAuthentication.

### State Transitions

```text
pending_email_verification -> pending_display_name -> active
```

A conta está apta à autenticação somente no estado `active`. A definição de senha não altera esse estado.

## Entity: AuthenticationChallenge

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | chave primária | Identifica uma emissão. |
| userId | BIGINT UNSIGNED | obrigatório após o cadastro inicial; referência a User | Titular da emissão. |
| purpose | enum | `email_verification` ou `passwordless_login` | Finalidade única da emissão. |
| secretHash | string | obrigatório | Hash do segredo opaco usado exclusivamente pelo link. |
| codeHash | string | obrigatório para novas emissões | Hash do código numérico de seis dígitos; nenhuma cópia recuperável é persistida. |
| expiresAt | timestamp | obrigatório | Exatamente 10 minutos após a emissão. |
| consumedAt | timestamp | opcional | Indica uso bem-sucedido. |
| supersededAt | timestamp | opcional | Indica substituição por emissão posterior da mesma finalidade. |
| failedAttempts | integer | obrigatório, padrão 0 | Controla tentativas incorretas do código; a emissão é excluída ao atingir o limite configurado, 3 por padrão. |
| rememberMeRequested | boolean | obrigatório, padrão `false` | Escolha de persistência recebida ao criar a emissão e aplicada na autenticação por código ou link. |
| createdAt | timestamp | obrigatório | Registro de emissão. |

### Relationships

- AuthenticationChallenge N:1 User.

### State Transitions

```text
active -> consumed
active -> superseded
active -> expired
```

Somente uma emissão `active` pode existir por usuário e finalidade. O consumo por link ou código é exclusivo e torna ambos inválidos. Emissões usadas ou substituídas são excluídas na operação que as encerra; emissões vencidas são rejeitadas no instante da verificação e removidas pela rotina de limpeza configurável ou pela próxima operação que as encontrar.

O link recebe um segredo opaco e o código recebe seis dígitos numéricos independentes. Somente seus hashes são persistidos, sem cópia recuperável de código ou token.

## Entity: Session

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| id | string | chave primária | Identificador opaco de sessão. |
| userId | BIGINT UNSIGNED | opcional antes da autenticação; referência a User após autenticar | Sessões técnicas anônimas podem existir para CSRF; uma sessão de acesso possui titular. |
| persistentAuthenticationId | BIGINT UNSIGNED | opcional; referência a PersistentAuthentication | Origem da sessão reconstruída, quando aplicável. |
| payload | blob | obrigatório | Estado de sessão protegido pelo framework. |
| lastActivityAt | timestamp | obrigatório | Última atividade observada. |

### Relationships

- Session N:1 User.

### State Transitions

```text
active -> deleted
```

Sessões encerradas ou invalidadas são excluídas imediatamente, sem retenção. Invalidar as demais sessões remove todas as sessões ativas do usuário, exceto a que realizou a operação, e permite preservar somente a autenticação persistente associada a ela.

## Entity: PersistentAuthentication

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | chave primária | Identifica uma autenticação persistente. |
| userId | BIGINT UNSIGNED | obrigatório; referência a User | Titular da credencial. |
| secretHash | string | obrigatório, único | Hash do segredo enviado somente no cookie persistente. |
| createdAt | timestamp | obrigatório | Registro da criação. |
| revokedAt | timestamp | opcional | Indica logout ou revogação. |
| lastUsedAt | timestamp | obrigatório | Última reconstrução de sessão observada. |

### Relationships

- PersistentAuthentication N:1 User.

### State Transitions

```text
active -> revoked
```

Uma credencial persistente somente existe quando o usuário seleciona “Manter-me conectado”. Ela não expira por inatividade por padrão, pode recriar uma sessão server-side perdida e é revogável. Invalidar as demais sessões também revoga todas as credenciais persistentes do usuário, exceto a associada à sessão atual, quando houver.

## Dados operacionais sem nova entidade de domínio

Limites de envio e tentativas são mantidos pelo mecanismo de limitação da aplicação com chaves separadas por e-mail e origem. Logs estruturados registram eventos de segurança mínimos por 30 dias por padrão, com configuração por ambiente; não há tabela de auditoria dedicada nesta fase.
