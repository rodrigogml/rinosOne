# Modelo de Dados — Fundação de Localidades

Todas as tabelas desta feature pertencem ao schema global `rinosone`. Seus identificadores são `BIGINT UNSIGNED AUTO_INCREMENT`. Não há tabelas de tenant nem referências core → tenant.

## Catálogo territorial

### `country`

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica imutável. |
| `isoAlpha2` | CHAR(2) | obrigatório, `uk_country_isoAlpha2` | Código ISO 3166-1 alpha-2. |
| `isoAlpha3` | CHAR(3) | obrigatório, `uk_country_isoAlpha3` | Código ISO 3166-1 alpha-3. |
| `isoNumeric` | CHAR(3) | obrigatório, `uk_country_isoNumeric` | Código ISO numérico. |
| `name` | VARCHAR(120) | obrigatório | Nome de exibição do país. |
| `activeForSelection` | BOOLEAN | obrigatório, indexado | Elegibilidade para uso futuro. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

### `brazilState`

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica. |
| `idCountry` | BIGINT UNSIGNED | FK obrigatória para `country` | País Brasil. |
| `ibgeCode` | CHAR(2) | obrigatório, `uk_brazilState_ibgeCode` | Código oficial IBGE. |
| `abbreviation` | CHAR(2) | obrigatório, `uk_brazilState_abbreviation` | Sigla oficial. |
| `name` | VARCHAR(120) | obrigatório | Nome oficial atual. |
| `activeForSelection` | BOOLEAN | obrigatório, indexado | Elegibilidade para uso futuro. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

### `brazilMunicipality`

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica. |
| `idBrazilState` | BIGINT UNSIGNED | FK obrigatória para `brazilState` | UF oficial. |
| `ibgeCode` | CHAR(7) | obrigatório, `uk_brazilMunicipality_ibgeCode` | Código oficial IBGE. |
| `name` | VARCHAR(160) | obrigatório | Nome oficial atual. |
| `activeForSelection` | BOOLEAN | obrigatório, indexado | Elegibilidade para uso futuro. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

## Referências postais

### `postalCode`

Representa somente um código postal dentro de um país; não representa endereço nem logradouro.

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica. |
| `idCountry` | BIGINT UNSIGNED | FK obrigatória para `country` | País do código postal. |
| `normalizedValue` | VARCHAR(32) | obrigatório | Valor somente com caracteres relevantes para comparação. |
| `displayValue` | VARCHAR(32) | obrigatório | Formatação recebida ou canônica para apresentação. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

`uk_postalCode_country_normalizedValue (idCountry, normalizedValue)` garante idempotência do código sem afirmar que ele identifica uma única localidade.

### `localityReference`

É o candidato selecionável de localidade postal. Pode representar logradouro, bairro, faixa ou apenas município, sem fabricar precisão que a fonte não forneça.

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica. |
| `idCountry` | BIGINT UNSIGNED | FK obrigatória para `country` | País da referência. |
| `idBrazilState` | BIGINT UNSIGNED | FK opcional para `brazilState` | UF reconciliada; exclusiva de dados brasileiros. |
| `idBrazilMunicipality` | BIGINT UNSIGNED | FK opcional para `brazilMunicipality` | Município reconciliado. |
| `localityKind` | VARCHAR(32) | obrigatório, indexado | `STREET`, `NEIGHBORHOOD`, `MUNICIPALITY`, `POSTAL_RANGE` ou `UNSPECIFIED`. |
| `streetType` | VARCHAR(80) | opcional | Tipo observado, como Avenida ou Rua. |
| `streetName` | VARCHAR(255) | opcional | Nome canônico/observado do logradouro. |
| `neighborhoodName` | VARCHAR(160) | opcional | Bairro observado. |
| `cityNameObserved` | VARCHAR(160) | opcional | Texto recebido quando não reconciliado. |
| `stateCodeObserved` | VARCHAR(32) | opcional | Sigla/código textual recebido quando não reconciliado. |
| `status` | VARCHAR(16) | obrigatório, indexado | Somente `ACTIVE` ou `REMOVED`. |
| `removalReason` | VARCHAR(40) | opcional | `DUPLICATE_AUTOMATIC`, `DUPLICATE_ADMINISTRATIVE`, `INVALID`, `OBSOLETE` ou `OTHER`. |
| `removedAt` | DATETIME(6) | opcional | Momento da remoção lógica atual. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

### `postalCodeLocalityReference`

Tabela de associação N:N entre CEP e referência postal.

| Campo | Tipo | Restrições |
| --- | --- | --- |
| `idPostalCode` | BIGINT UNSIGNED | PK composta e FK obrigatória para `postalCode` |
| `idLocalityReference` | BIGINT UNSIGNED | PK composta e FK obrigatória para `localityReference` |
| `createdAt` | DATETIME(6) | obrigatório |

### `localityReferenceObservation`

Preserva a proveniência operacional de cada referência, permite encontrar novamente item removido e impede que a normalização destrua informações recebidas.

| Campo | Tipo | Restrições | Finalidade |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica. |
| `idLocalityReference` | BIGINT UNSIGNED | FK obrigatória para `localityReference` | Referência resultante. |
| `sourceKey` | VARCHAR(64) | obrigatório | Ex.: `viacep`, `brasilapi`. |
| `externalIdentifier` | VARCHAR(255) | opcional | Identificador estável do provedor quando disponível. |
| `identitySignature` | CHAR(64) | obrigatório | SHA-256 da identidade estável dentro da fonte; inclui identificador externo quando publicado. |
| `equivalenceSignature` | CHAR(64) | opcional, indexado | SHA-256 dos campos que comprovam equivalência entre fontes; fica `NULL` quando o conjunto forte estiver incompleto. |
| `observedPayload` | JSON | obrigatório | Campos públicos e normalizados necessários à reavaliação; sem segredo. |
| `firstSeenAt` | DATETIME(6) | obrigatório | Primeira incorporação. |
| `lastSeenAt` | DATETIME(6) | obrigatório, indexado | Última confirmação pela fonte. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatório | Auditoria temporal local. |

Índices únicos parciais não existem em MySQL. A implementação usa `uk_localityObservation_source_signature (sourceKey, identitySignature)` e preenche `identitySignature` mesmo quando houver `externalIdentifier`. `equivalenceSignature` não é única e só é preenchida com o conjunto forte completo; ela permite localizar candidatos para a regra conservadora de duplicidade.

## Integridade referencial

| Relação | Regra |
| --- | --- |
| `brazilState.idCountry → country.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| `brazilMunicipality.idBrazilState → brazilState.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| `postalCode.idCountry → country.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| `localityReference.idCountry → country.id` | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| `localityReference.idBrazilState → brazilState.id` | `ON UPDATE CASCADE`, `ON DELETE SET NULL` |
| `localityReference.idBrazilMunicipality → brazilMunicipality.id` | `ON UPDATE CASCADE`, `ON DELETE SET NULL` |
| Associação e observação → entidade-mãe | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |

Não se usa `RESTRICT` ou `NO ACTION`. A remoção de uma referência postal importada é lógica, portanto não depende de `DELETE` físico. Os dados de proveniência fazem parte do catálogo e não seguem a retenção de 90 dias dos históricos do Hub.

## Regras de persistência

- País, UF e Município sofrem *upsert* por códigos oficiais estáveis; nomes podem ser atualizados pela fonte IBGE.
- A rotina IBGE nunca apaga nem torna inativa uma referência territorial apenas porque ela não veio em uma execução.
- CEP é criado ou encontrado por país e valor normalizado. Uma referência nova é ligada a ele sem pressupor unicidade.
- Observação já conhecida atualiza somente `lastSeenAt` e a carga pública observada; seu `localityReference.status = REMOVED` permanece inalterado.
- Remoção e reativação futuras alteram apenas o estado atual e a justificativa; o registro de ação pertence aos logs administrativos da futura feature, não a uma tabela de carga desta fundação.
