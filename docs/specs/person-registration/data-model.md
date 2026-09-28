# Modelo de Dados — Cadastro de Pessoas por Organização

Todos os objetos abaixo pertencem ao schema isolado da organização. Identificadores persistidos usam `BIGINT UNSIGNED AUTO_INCREMENT`. O catálogo corporativo nunca referencia estas tabelas.

## Entidade: `person`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade técnica da Pessoa no schema organizacional. |
| `personType` | ENUM | obrigatório | `PF` ou `PJ`. |
| `name` | VARCHAR(255) | obrigatório | Nome completo da PF ou razão social da PJ. |
| `alias` | VARCHAR(255) | opcional | Apelido da PF ou nome fantasia da PJ. |
| `displayName` | VARCHAR(511) | obrigatório, indexado | Derivado de `name` e `alias`; não é único. |
| `cpf` | CHAR(11) | opcional, único | Somente PF; apenas dígitos normalizados. |
| `cnpj` | CHAR(14) | opcional, único | Somente PJ; apenas dígitos normalizados. |
| `rg` / `rgIssuer` | VARCHAR(40) / VARCHAR(60) | opcionais | Documento e órgão emissor da PF. |
| `pisNis` | VARCHAR(20) | opcional | Documento complementar da PF, normalizado quando aplicável. |
| `passportNumber` | VARCHAR(40) | opcional | Documento internacional. |
| `foreignDocumentNumber` | VARCHAR(60) | opcional | Documento estrangeiro. |
| `birthDate` | DATE | opcional | Permitido somente para PF; não pode ser futuro. |
| `foundationDate` | DATE | opcional | Permitido somente para PJ; não pode ser futuro. |
| `notes` | MEDIUMTEXT | opcional | Observações livres; não contém dados fiscais ou profissionais nesta fase. |
| `status` | ENUM | obrigatório | `ACTIVE` ou `INACTIVE`; inicia em `ACTIVE`. |
| `version` | BIGINT UNSIGNED | obrigatório | Inicia em 1 e avança a cada alteração para detectar gravação concorrente. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal. |

### Índices e regras

- `uk_person_cpf (cpf)` e `uk_person_cnpj (cnpj)` aceitam múltiplos valores nulos, mas impedem a repetição de documento informado, inclusive em Pessoas inativas.
- Índices de busca em `displayName`, `name`, `alias`, `cpf` e `cnpj` atendem a listagem e a busca rápida.
- O domínio garante a compatibilidade entre `personType`, documento e datas aplicáveis; o banco reforça as regras simples que possam ser expressas com segurança.

### Transições de estado

```text
ACTIVE --inactivate--> INACTIVE
INACTIVE --reactivate--> ACTIVE
ACTIVE | INACTIVE --physical delete--> removed
```

## Entidade: `personAddress`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade do endereço final. |
| `idPerson` | BIGINT UNSIGNED | FK obrigatória | Pessoa titular. |
| `label` | VARCHAR(60) | obrigatório | Identificação do endereço para o usuário. |
| `addressType` | ENUM | obrigatório | `RESIDENTIAL`, `COMMERCIAL`, `BILLING`, `DELIVERY`, `BRANCH` ou `OTHER`. |
| `idCountry` | BIGINT UNSIGNED | FK obrigatória para catálogo corporativo | País sempre selecionado do catálogo. |
| `idBrazilState` | BIGINT UNSIGNED | FK opcional para catálogo corporativo | Obrigatório no domínio quando o país for Brasil. |
| `idBrazilMunicipality` | BIGINT UNSIGNED | FK opcional para catálogo corporativo | Obrigatório no domínio quando o país for Brasil. |
| `idLocalityReference` | BIGINT UNSIGNED | FK opcional para catálogo corporativo | Localidade reconhecida, quando houver. |
| `stateText` / `cityText` | VARCHAR(120) | opcionais | Dados textuais para endereços fora do Brasil. |
| `street` | VARCHAR(255) | opcional | Rua/logradouro informado ou apresentado a partir da referência escolhida. |
| `number` | VARCHAR(40) | opcional | Preserva `S/N`, letras e referências quilométricas. |
| `complement` / `district` / `reference` | VARCHAR(255) | opcionais | Complemento, bairro e ponto de referência. |
| `postalCode` | VARCHAR(24) | opcional | Código postal conforme o país. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal. |

## Entidade: `personContact`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade do contato. |
| `idPerson` | BIGINT UNSIGNED | FK obrigatória | Pessoa titular. |
| `contactType` | ENUM | obrigatório | `EMAIL`, `PHONE`, `MOBILE`, `WHATSAPP`, `WEBSITE` ou `OTHER`. |
| `value` | VARCHAR(512) | obrigatório | Valor preservado para apresentação. |
| `normalizedValue` | VARCHAR(512) | obrigatório, indexado | Valor usado por validação e busca. |
| `description` | VARCHAR(255) | opcional | Observação sobre o contato. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal. |

Não há atributo de contato principal nesta fase.

## Entidade: `personBankAccount`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade da conta. |
| `idPerson` | BIGINT UNSIGNED | FK obrigatória | Pessoa titular. |
| `label` | VARCHAR(60) | obrigatório | Identificação da conta para o usuário. |
| `idFinancialInstitution` | BIGINT UNSIGNED | FK opcional para catálogo corporativo | Instituição selecionada quando disponível. |
| `accountType` | ENUM | obrigatório | `CHECKING`, `SAVINGS`, `INVESTMENT`, `SALARY` ou `OTHER`. |
| `agency` / `agencyDigit` | VARCHAR(32) | opcionais | Agência e dígito textual. |
| `accountNumber` / `accountDigit` | VARCHAR(64) / VARCHAR(32) | opcionais | Número e dígito textual. |
| `status` | ENUM | obrigatório | `ACTIVE` ou `INACTIVE`; inicia em `ACTIVE`. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal. |

Não há marcação de conta principal, salário ou finalidade nesta fase.

## Entidade: `personPixKey`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade da chave. |
| `idPerson` | BIGINT UNSIGNED | FK obrigatória | Pessoa titular. |
| `keyType` | ENUM | obrigatório | `CPF`, `CNPJ`, `EMAIL`, `PHONE` ou `RANDOM`. |
| `keyValue` | VARCHAR(255) | obrigatório | Valor informado para apresentação. |
| `normalizedKeyValue` | VARCHAR(255) | obrigatório | Valor validado e normalizado. |
| `status` | ENUM | obrigatório | `ACTIVE` ou `INACTIVE`; inicia em `ACTIVE`. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal. |

O índice único `(idPerson, keyType, normalizedKeyValue)` impede repetir a mesma chave para a mesma Pessoa, sem impor exclusividade entre Pessoas.

## Entidade: `personRelationship`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade do vínculo. |
| `idSourcePerson` | BIGINT UNSIGNED | FK obrigatória | Pessoa a partir da qual o tipo é declarado. |
| `idTargetPerson` | BIGINT UNSIGNED | FK obrigatória | Outra ponta do vínculo. |
| `relationshipType` | ENUM | obrigatório | Tipo direcional padronizado. |
| `description` | VARCHAR(1000) | opcional | Nota livre sobre o vínculo. |
| `createdAt` / `updatedAt` | DATETIME(6) | obrigatórios | Auditoria temporal. |

O domínio impede que origem e destino sejam a mesma Pessoa. O índice único `(idSourcePerson, idTargetPerson, relationshipType)` evita repetir o mesmo vínculo direcional.

### Tipos e apresentação oposta

| Tipo armazenado | Tipo apresentado na outra Pessoa |
| --- | --- |
| `CHILD_OF` | `PARENT_OF` |
| `PARENT_OF` | `CHILD_OF` |
| `GRANDCHILD_OF` | `GRANDPARENT_OF` |
| `GRANDPARENT_OF` | `GRANDCHILD_OF` |
| `SPOUSE_OF` | `SPOUSE_OF` |
| `PARTNER_OF` | `PARTNER_OF` |
| `EMPLOYEE_OF` | `EMPLOYER_OF` |
| `EMPLOYER_OF` | `EMPLOYEE_OF` |
| `CONTRACTOR_OF` | `CONTRACTING_PARTY_OF` |
| `CONTRACTING_PARTY_OF` | `CONTRACTOR_OF` |
| `OTHER` | `OTHER` |

Os rótulos localizados podem usar linguagem inclusiva e contexto de apresentação sem alterar o tipo persistido.

## Entidade: `personAuditEvent`

| Campo | Tipo | Restrições | Observações |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | PK | Identidade do evento. |
| `personId` | BIGINT UNSIGNED | obrigatório, sem FK | Identificador histórico da Pessoa, preservado após exclusão física. |
| `idActorUser` | BIGINT UNSIGNED | FK opcional para catálogo corporativo | Usuário que provocou a ação quando identificado. |
| `action` | ENUM | obrigatório | `CREATED`, `UPDATED`, `INACTIVATED`, `REACTIVATED` ou `DELETED`. |
| `occurredAt` | DATETIME(6) | obrigatório, indexado | Momento da ação. |
| `correlationId` | VARCHAR(128) | opcional, indexado | Correlação operacional segura. |

## Integridade referencial

| Relação | Regra |
| --- | --- |
| Dados filhos de Pessoa → `person` | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| `personRelationship` origem/destino → `person` | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| Endereço → País corporativo | `ON UPDATE CASCADE`, `ON DELETE CASCADE` |
| Endereço → UF, Município e Localidade corporativos | `ON UPDATE CASCADE`, `ON DELETE SET NULL` |
| Conta → Instituição Financeira corporativa | `ON UPDATE CASCADE`, `ON DELETE SET NULL` |
| Evento de auditoria → Usuário corporativo | `ON UPDATE CASCADE`, `ON DELETE SET NULL` |

Não há FK do catálogo corporativo para o schema organizacional. Nenhuma FK desta feature usa `RESTRICT` ou `NO ACTION`.
