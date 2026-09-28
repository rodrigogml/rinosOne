# Plano Técnico — Fundação de Localidades

## Resumo

Implementar o catálogo global de referências territoriais e postais: Países, UFs, Municípios, CEPs e referências de localidades/logradouros. O IBGE abastece automaticamente a camada territorial brasileira; ViaCEP e BrasilAPI enriquecem referências postais sob demanda. A consulta nunca espera por fonte externa: devolve a base local e continua a consolidação em fila.

## Contexto técnico

| Aspecto | Decisão |
| --- | --- |
| Aplicação | Laravel/PHP, Eloquent, MySQL, API JSON e Vue 3 já existentes. |
| Identidade | PKs `BIGINT UNSIGNED AUTO_INCREMENT`; códigos IBGE e ISO são atributos de negócio. |
| Ownership | Catálogo core global. Futuras tabelas de tenant poderão referenciá-lo; nunca o contrário. |
| Integridade | FKs sem `RESTRICT`/`NO ACTION`, com `ON UPDATE CASCADE` e `CASCADE` ou `SET NULL` conforme obrigatoriedade. |
| Fonte territorial | API oficial IBGE. |
| Fontes postais iniciais | ViaCEP e BrasilAPI, habilitáveis por configuração e chamadas em paralelo. |
| Assíncrono | Fila persistente padrão (`database`) e cache compartilhado padrão (`database`) para lock/estado efêmero. |
| Hub | Integração explícita, sem interface/registro genérico de rotinas. |

## Arquitetura proposta

```text
IBGE ──> IbgeTerritorySource ──> TerritoryCatalogSynchronizationService
                                      │
Scheduler horário ─> IbgeTerritoryMaintenanceService ─> histórico existente ─> Hub

Consumidor futuro ─> API de consulta ─> catálogo local ─> resposta imediata
                           │
                           └─> PostalReferenceEnrichmentJob
                                   ├─> ViaCepPostalReferenceSource
                                   ├─> BrasilApiPostalReferenceSource
                                   └─> PostalReferenceConsolidationService ─> catálogo/proveniência
```

## Componentes e responsabilidades

| Local | Responsabilidade |
| --- | --- |
| `database/migrations/core` | Criar as sete tabelas globais, FKs, índices e unicidades descritos em [data-model.md](data-model.md). |
| `app/Models` | Modelos Eloquent do catálogo e relações explícitas. |
| `app/Infrastructure/Locality` | Contratos e adaptadores HTTP `IbgeTerritorySource`, `PostalReferenceSource`, `ViaCep...` e `BrasilApi...`; normalização de contratos externos. |
| `app/Services/Locality` | Sincronização territorial, consulta local, consolidação postal, assinatura de observação e equivalência forte. |
| `app/Jobs/Locality` | Enriquecimento postal que continua fora do ciclo HTTP; recebe somente país e CEP normalizados. |
| `app/Services/Maintenance` | Rotina IBGE singleton e integração explícita adicional no `MaintenanceHubService`. |
| `app/Http/Controllers/Api/V1` | Fronteira futura de consulta postal e extensão de leitura do Hub. |
| `routes/console.php` | Avaliação horária da rotina IBGE, cujo serviço decide se está devida. |
| `config/localities.php` | Fontes habilitadas, timeouts, lock, atraso pós-falha, TTL do estado de consulta e limites seguros. |
| `tests` | Testes unitários de normalização/reconciliação e testes de feature/integração dos contratos, filas, locks e Hub. |

## Fluxos principais

### Atualização territorial IBGE

1. O scheduler chama a rotina uma vez por hora.
2. A rotina tenta obter `Cache::lock('maintenance.locality-ibge-territory-catalog')`; se indisponível, recusa silenciosamente a concorrência.
3. Dentro do lock, a rotina verifica o último sucesso no `maintenanceExecutionHistory`: executa sem sucesso anterior, após um mês do último sucesso ou após uma falha cujo atraso configurado tenha vencido.
4. Cria histórico `RUNNING` com expiração pela configuração global de manutenção, consulta IBGE e faz *upsert* por ISO/IBGE em transação.
5. Atualiza o histórico para `SUCCEEDED` ou `FAILED` com resumo e contagens seguras. Falha nunca remove dados do catálogo.
6. O Hub lê a rotina diretamente, conforme [contracts/maintenance-routine.md](contracts/maintenance-routine.md), e não oferece ação manual.

### Consulta e enriquecimento de CEP

1. A API normaliza país e CEP, busca `localityReference.status = ACTIVE` ligado ao `postalCode` e devolve os candidatos imediatamente.
2. Se ainda não houver atualização pendente para a chave no cache, a API marca `PENDING` e enfileira um único `PostalReferenceEnrichmentJob` após o *commit*.
3. O job obtém lock por chave postal, chama cada adaptador habilitado em paralelo e isola falhas por fonte.
4. O consolidado associa município somente por código IBGE conhecido, preserva texto observado, reaproveita observação já existente e vincula CEP/referência N:N.
5. Equivalência forte pode marcar apenas a referência redundante como `REMOVED`; conflitos permanecem `ACTIVE`. Uma referência removida reencontrada mantém o estado.
6. O job atualiza no cache o estado final; o consumidor pode consultar [o contrato postal](contracts/postal-reference-lookup.md) a cada segundo até a conclusão ou abandonar a observação.

## Estratégia de persistência e concorrência

- *Upsert* territorial: `country` por ISO alpha-2, `brazilState` por código IBGE, `brazilMunicipality` por código IBGE.
- *Upsert* postal: `postalCode` por `(idCountry, normalizedValue)`; observação por `(sourceKey, identitySignature)`; associação CEP/referência por sua PK composta.
- Uma mesma observação não cria segunda referência quando o job for reexecutado. A `identitySignature` é específica da fonte; a `equivalenceSignature` é calculada somente com o conjunto completo de campos fortes e fica `NULL` nos demais casos, permitindo comparar fontes distintas sem confundir suas identidades.
- Lock de manutenção e lock postal são separados. Um CEP lento não bloqueia outro CEP nem a atualização territorial.
- `REMOVED` é estado de negócio persistente. `PENDING`/resultado de tentativa externa é estado efêmero de cache, não tabela de carga nem auditoria.

## Segurança, privacidade e observabilidade

- As rotas postais terão autorização de capacidade quando houver seu primeiro consumidor; o contrato já reserva a resposta `403` sem assumir um modelo de tenant.
- A rotina IBGE usa permissão exclusiva de leitura de Hub. Não há permissão de disparo manual.
- URLs, *payloads*, cabeçalhos, exceções e mensagens das fontes não chegam ao cliente; logs estruturados usam somente contexto seguro e `failureCode` classificável.
- Os históricos e auditorias já existentes obedecem às duas retenções configuráveis de manutenção. O catálogo e sua proveniência não expiram por essa política, pois são dados de negócio e necessários para manter uma remoção suprimida.

## Interfaces humanas

**Aplicabilidade de Interface Design: aplicável e especificada.** A rotina IBGE altera pontualmente a superfície existente `SURF-WEB-MAINTENANCE`; a interação, seus estados e a ausência de ação manual estão definidos em [interface-spec.md](interface-spec.md). A tela de consulta de CEP e a administração de localidades continuam explicitamente adiadas e exigirão especificações próprias antes de implementação.

## Ordem de implementação

1. Criar migrations e modelos do catálogo, com testes de integridade e relações.
2. Implementar adaptador IBGE, sincronização idempotente e rotina singleton com política `auto`.
3. Registrar configuração, scheduler, permissão de leitura e composição explícita no Hub; cobrir lista/detalhe sem ação manual.
4. Implementar contratos internos e adaptadores ViaCEP/BrasilAPI com testes HTTP simulados.
5. Implementar consulta local, job de enriquecimento, cache de estado e consolidação conservadora.
6. Expor e testar os endpoints de referência postal, inclusive *polling*, falha parcial, saída local e abandono do cliente.
7. Executar os cenários de [quickstart.md](quickstart.md), revisão de segurança e `migrate:fresh` em banco de teste.

## Validação obrigatória

- Migrations em ordem limpa, incluindo FKs, PKs BIGINT, índices e ausência de ações restritivas.
- IBGE: primeira execução, mensalidade, renomeação, falha, lock e ausência sem exclusão.
- CEP: resultados locais antes de HTTP externo, fontes paralelas, resposta parcial, persistência assíncrona, idempotência e pausa visual sem cancelamento do job.
- Qualidade: CEP N:N com referências, conflito territorial coexistente, equivalência forte removida, removed persistente e reativação futura possível.
- Hub: leitura autorizada, rotina `NOT_EXECUTED`, agenda correta, histórico seguro e nenhuma capacidade de disparo.

## Limites desta fase

- Não cria endereço final, número, complemento ou relação de endereço com Pessoas/tenants.
- Não cria catálogo de nacionalidades, subdivisões estrangeiras ou municípios estrangeiros.
- Não entrega tela própria de CEP nem administração de remoção/reativação.
- Não implementa Correios como provedor inicial.
- Não cria tabela genérica de rotina ou auditoria exclusiva de carga/consulta.
