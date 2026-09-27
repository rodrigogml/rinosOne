# Plano Técnico — Catálogo de Instituições Financeiras

## Resumo

Implementar o catálogo global `financialInstitution` no schema `rinosone`, abastecido exclusivamente pelo serviço público BcBase v2 do Banco Central do Brasil (BCB). A atualização diária é acionada pelo ponto específico da Central de Manutenções; o catálogo não cria nem conhece uma agenda própria.

## Contexto técnico

| Aspecto | Decisão |
|---|---|
| Aplicação | Laravel/PHP, MySQL e Eloquent |
| Fonte | BcBase v2 / `EntidadesSupervisionadas` do BCB |
| Identidade de reconciliação | `bcbEntityIdentifier` (`codigoIdentificadorBacen`) |
| Persistência | Tabela core global, PK `BIGINT UNSIGNED AUTO_INCREMENT` |
| Execução | Serviço síncrono e invocável; agenda diária específica da Central de Manutenções |
| Observabilidade | Logs estruturados de início, resultado, falha e contagens; sem tabela de auditoria |
| TLS da fonte | A validação de certificado permanece ativa. `BCB_CA_BUNDLE` aceita um bundle PEM confiável; no desenvolvimento local, a aplicação usa `storage/app/certificates/cacert.pem` quando existente. |

## Fluxo de atualização

1. O futuro módulo de manutenção invoca o serviço de sincronização, informando opcionalmente a data de referência para teste.
2. O adaptador BCB consulta a coleção `EntidadesSupervisionadas` daquela data e percorre todas as páginas.
3. O serviço normaliza os campos e faz *upsert* por `bcbEntityIdentifier`; classificações que a fonte não publicar são preservadas como `NULL`.
4. O status BCB `3 — Autorizada em Atividade` define `activeForSelection`; os outros estados a tornam indisponível para seleção.
5. Registros ausentes da resposta não são excluídos nem inativados. Falhas da fonte não alteram o catálogo existente.
6. O serviço emite o resultado consolidado no log e devolve um objeto de resultado para o chamador e os testes.

## Componentes previstos

- `database/migrations/core`: cria a tabela global, índices de consulta e unicidade somente para a chave BCB.
- `app/Models/FinancialInstitution.php`: modelo core com identificador BIGINT.
- `app/Infrastructure/FinancialInstitution`: contrato e adaptador HTTP do BcBase, isolando o formato OData do restante da aplicação.
- `app/Services/FinancialInstitution`: serviço de sincronização, normalização, determinação de disponibilidade e resultado da execução.
- `config`: parâmetros públicos da fonte e canal de log apropriado, sem credenciais.
- `tests`: chamadas diretas ao serviço com respostas BCB simuladas, cobrindo criação, atualização idempotente, disponibilidade, ausência e falha.

## Modelo e relacionamentos

O modelo detalhado está em [data-model.md](data-model.md). A tabela pertence ao core e não possui FK para tenants. Funcionalidades de tenant poderão referenciá-la por `idFinancialInstitution`; essas FKs usarão `ON UPDATE CASCADE` e `ON DELETE CASCADE` quando obrigatórias ou `ON DELETE SET NULL` quando opcionais. Não serão criadas referências no sentido core → tenant nem ações restritivas.

## Limites desta fase

- Não haverá edição manual de dados, endpoint público ou comando operacional exposto. A visualização e o disparo administrativo pertencem à Central de Manutenções.
- Não haverá scheduler, cron, fila periódica, tarefa autônoma ou configuração em `routes/console`.
- Dados de participação Pix, histórico de eventos e trilha de cargas não serão persistidos.
- ISPB e COMPE permanecem campos opcionais para futura fonte oficial BCB que os forneça.

## Validação

- A migration cria os tipos, índices e regras de chaves definidos no modelo de dados.
- Uma chamada de teste executa a sincronização e comprova que a repetição é idempotente.
- Status BCB ativo/inativo altera somente `activeForSelection`, sem apagar registros.
- Ausência temporária ou indisponibilidade da fonte preserva os dados anteriores e produz log de falha.

## Conformidade arquitetural

O desenho mantém `BIGINT` como identificador interno, permite apenas relações tenant → core e evita `RESTRICT`/`NO ACTION`. A atualização é desacoplada da agenda: a Central de Manutenções a invoca diariamente pelo seu ponto específico, enquanto o serviço protege a rotina com bloqueio singleton compartilhado por disparos manuais e programados.
