# Modelo de Dados Catálogo de Instituições Financeiras

Todos os campos abaixo pertencem ao schema global `rinosone`. A tabela não possui FK para tenants. Futuras tabelas de tenant poderão referenciá-la somente na direção tenant para core, conforme a Constituição.

## Entidade: `financialInstitution`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK, auto incremento | Identidade técnica imutável do sistema. |
| `bcbEntityIdentifier` | VARCHAR(32) | obrigatório, único | `codigoIdentificadorBacen`; chave de reconciliação da fonte. |
| `bcbReferenceDate` | DATE | obrigatório | Data-base do recorte recebido do BCB. |
| `sisbacenCode` | VARCHAR(32) | opcional, indexado | Código Sisbacen publicado pela fonte. |
| `cnpj` | CHAR(14) | opcional, indexado | Maiúsculo alfanumérico; sem unicidade. |
| `ispb` | CHAR(8) | opcional, indexado | Atributo pesquisável quando publicado por fonte BCB adotada futuramente. |
| `compeCode` | CHAR(3) | opcional, indexado | Preserva zeros à esquerda; sem unicidade. |
| `legalName` | VARCHAR(255) | obrigatório | Denominação oficial. |
| `reducedName` | VARCHAR(255) | obrigatório | Nome preferencial de apresentação e busca. |
| `tradeName` | VARCHAR(255) | opcional | Nome fantasia publicado pelo BCB. |
| `acronym` | VARCHAR(64) | opcional | Sigla publicada pelo BCB. |
| `bcbStatusCode` | VARCHAR(16) | opcional | Código oficial da situação de funcionamento, quando publicado pelo BCB. |
| `bcbStatusName` | VARCHAR(128) | opcional | Descrição oficial da situação, quando publicada pelo BCB. |
| `institutionTypeCode` | VARCHAR(16) | opcional | Código oficial do segmento, quando publicado pelo BCB. |
| `institutionTypeName` | VARCHAR(128) | opcional | Descrição oficial do segmento, quando publicada pelo BCB. |
| `activeForSelection` | BOOLEAN | obrigatório | Derivado exclusivamente de `bcbStatusCode`; verdadeiro somente para a situação oficial autorizada em atividade. |
| `lastSynchronizedAt` | DATETIME(6) | obrigatório | Momento da última atualização bem-sucedida do registro. |
| `createdAt` | DATETIME(6) | obrigatório | Criação local. |
| `updatedAt` | DATETIME(6) | obrigatório | Última alteração local. |

### Índices e Restrições

- `uk_financial_institution_bcb_entity_identifier` em `bcbEntityIdentifier`.
- `idx_financial_institution_sisbacen_code`, `idx_financial_institution_cnpj`, `idx_financial_institution_ispb` e `idx_financial_institution_compe_code` para pesquisa exata.
- `idx_financial_institution_selection_name` em `activeForSelection, reducedName` para a seleção padrão.
- Não criar unicidade para CNPJ, ISPB, COMPE ou código Sisbacen.
- Não armazenar participação Pix, payload bruto da fonte ou tabelas de execução de carga.

### Transições de Disponibilidade

```text
status BCB = Autorizada em Atividade -> activeForSelection = true
outro status ou status ausente        -> activeForSelection = false
ausência do registro em resposta      -> mantém o valor atual e gera alerta em log
```

### Relacionamentos Futuros

Uma conta bancária em schema de tenant poderá apontar para `financialInstitution.id`. A FK deverá usar `BIGINT UNSIGNED`, `ON UPDATE CASCADE` e `ON DELETE CASCADE` quando a conta não puder existir sem a instituição, ou `ON DELETE SET NULL` quando o vínculo for opcional. A instituição financeira nunca referencia tabela de tenant.
