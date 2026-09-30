# Modelo de Dados: Compatibilidade de Schemas e Guarda Operacional

## Fonte de verdade existente: histórico de migrations

Cada schema mantém seu histórico de migrations. A compatibilidade é derivada da presença de todas as migrations pertencentes ao catálogo distribuído com a versão em execução.

- O histórico do schema global determina se a plataforma pode atender funções de negócio.
- O histórico de cada schema organizacional determina se aquela organização pode atender funções contextuais.
- Histórico ausente, inacessível ou incompleto é incompatível por definição.

Não haverá uma cópia manual do número de versão do catálogo.

## Entidade: `tenantSchemaUpdate`

Representa um ciclo de atualização posterior ao provisionamento inicial de uma organização. Pertence ao schema global e é controle operacional, não dado de negócio de tenant.

| Campo | Tipo | Restrições | Notas |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PK, auto incremento | Identidade do ciclo. |
| idTenant | BIGINT UNSIGNED | obrigatório, FK para tenant; ON UPDATE CASCADE, ON DELETE CASCADE | Organização que será atualizada. |
| targetCatalog | string | obrigatório | Identificador derivado do catálogo alvo, suficiente para diagnosticar e impedir duplicação sem expor detalhes ao usuário comum. |
| state | enum | obrigatório | `QUEUED`, `RUNNING`, `SUCCEEDED` ou `FAILED`. |
| attemptCount | inteiro sem sinal | obrigatório, padrão 0 | Tentativas já iniciadas. |
| lastFailureCode | string | opcional | Código operacional seguro, sem mensagem de banco ou segredo. |
| startedAt | timestamp | opcional | Início da tentativa em execução. |
| completedAt | timestamp | opcional | Conclusão de sucesso ou falha terminal. |
| createdAt | timestamp | obrigatório | Criação do ciclo. |
| updatedAt | timestamp | obrigatório | Última mudança de estado. |

### Relacionamentos

- Uma organização possui zero ou mais ciclos de atualização.
- Uma atualização referencia somente a organização global, nunca recebe FK de volta do schema da organização.
- O registro mais recente não concluído determina indisponibilidade por atualização; uma restrição de unicidade deve impedir mais de um ciclo ativo por organização.

### Transições de estado

```text
QUEUED -> RUNNING -> SUCCEEDED
QUEUED -> RUNNING -> QUEUED  (falha transitória e nova tentativa)
QUEUED -> RUNNING -> FAILED  (falha terminal ou tentativas esgotadas)
```

## Estados derivados de disponibilidade

| Condição | Disponibilidade da plataforma | Disponibilidade da organização |
| --- | --- | --- |
| Catálogo global completo | Disponível | Avaliada individualmente |
| Catálogo global incompleto ou ilegível | Indisponível | Nenhuma operação organizacional é atendida |
| Catálogo da organização completo, sem atualização pendente | Disponível | Disponível, sujeito a membership, autorização e estado administrativo |
| Atualização `QUEUED` ou `RUNNING` | Plataforma disponível | Temporariamente indisponível por atualização |
| Atualização `FAILED` ou catálogo organizacional incompleto | Plataforma disponível | Indisponível até recuperação validada |
| Organização administrativamente inativa | Plataforma disponível | Indisponível por decisão administrativa, independentemente da compatibilidade |
