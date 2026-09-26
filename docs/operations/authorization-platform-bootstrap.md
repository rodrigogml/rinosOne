# Bootstrap do primeiro administrador PLATFORM

> [!IMPORTANT]
> Este procedimento é exclusivo da infraestrutura. Cadastro, autenticação e criação de tenant nunca atribuem roles `PLATFORM`.

## Pré-requisitos

- A pessoa já existe em `user` e possui e-mail verificado.
- O catálogo contém permissions `PLATFORM` e a role de sistema `platform.administrator` foi criada pela migração operacional aprovada.
- A alteração possui ticket, operador responsável e janela de execução registrados.

## Procedimento

1. Em uma transação administrativa, confirmar a pessoa verificada e localizar a role `platform.administrator`.
2. Inserir o assignment diretamente no schema core, sem `idTenant`, registrando o identificador do ticket no log operacional:

```sql
INSERT INTO auth_role_assignment (idRole, idUser, idTenant, state, createdAt, updatedAt)
SELECT r.id, u.id, NULL, 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM auth_role r
JOIN user u ON u.id = :verifiedUserId
WHERE r.key = 'platform.administrator'
  AND r.scope = 'PLATFORM'
  AND u.emailVerifiedAt IS NOT NULL;
```

3. Confirmar que exatamente um assignment foi criado, validar uma operação protegida de plataforma e registrar o resultado no ticket.
4. Em caso de falha, fazer rollback da transação. Não compensar criando role, permission ou usuário fora do procedimento aprovado.

## Auditoria operacional

Registrar ticket, operador, data/hora, `idUser`, `idRole`, ambiente, motivo e evidência da validação. Não registrar senha, token, sessão ou outro segredo.
