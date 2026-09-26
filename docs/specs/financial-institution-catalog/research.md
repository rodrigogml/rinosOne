# Pesquisa Técnica Catálogo de Instituições Financeiras

## Decisão 1: BcBase como fonte única do catálogo

**Decisão**: Consumir somente o recurso BcBase `EntidadesSupervisionadas` do BCB para a carga inicial e para cada atualização diária futura.

**Racional**: a fonte disponibiliza, no mesmo recorte diário, o identificador da entidade, CNPJ, código Sisbacen, nomes, segmento e situação de funcionamento. Isso evita conciliar fontes por CNPJ, nome ou código bancário. A relação paralela de instituições em funcionamento não será consumida nesta fase.

**Alternativas consideradas**: combinar BcBase e a relação de instituições em funcionamento foi rejeitado porque introduziria reconciliação entre feeds sem uma chave comum aprovada. Usar fonte privada foi rejeitado pelo requisito de fonte exclusiva BCB.

Referências: [dataset BcBase](https://dadosabertos.bcb.gov.br/dataset/dados-cadastrais-de-entidades-autorizadas) e [documentação OData BcBase v2](https://olinda.bcb.gov.br/olinda/servico/BcBase/versao/v2/documentacao).

## Decisão 2: Identidade de reconciliação publicada pelo BCB

**Decisão**: Usar `codigoIdentificadorBacen`, mapeado para `bcbEntityIdentifier`, como identificador externo único do catálogo.

**Racional**: o BCB descreve esse valor como o identificador automaticamente gerado da pessoa física ou jurídica em seu cadastro único. Ele preserva a identidade do registro mesmo que CNPJ, nomes ou códigos operacionais mudem.

**Alternativas consideradas**: CNPJ, ISPB, COMPE e código Sisbacen foram rejeitados como chaves de reconciliação, pois são atributos de negócio pesquisáveis e não garantem a identidade histórica do registro BCB.

## Decisão 3: CNPJ alfanumérico sem unicidade local

**Decisão**: Armazenar CNPJ em 14 posições alfanuméricas maiúsculas, indexado e sem restrição de unicidade.

**Racional**: a Receita Federal adotou CNPJ alfanumérico para novas inscrições em 2026, mantendo válidos os números existentes. A falta de uma garantia contratual de identidade histórica pelo CNPJ impede tratá-lo como chave única do catálogo.

**Alternativas consideradas**: restringir a dígitos foi rejeitado por incompatibilidade com inscrições novas. Aplicar unicidade local foi rejeitado porque pode bloquear a preservação de registros históricos ou a evolução oficial da fonte.

Referência: [Receita Federal - CNPJ alfanumérico](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/cnpj-alfanumerico/cnpj-alfa?cl=en&set_language=pt-br).

## Decisão 4: Atualização invocável, com agenda exclusiva da central

**Decisão**: A feature expõe uma operação explícita, idempotente e testável de atualização. A agenda diária é registrada exclusivamente no ponto específico da Central de Manutenções; o catálogo não registra tarefa ou agenda autônoma.

**Racional**: a atualização diária foi aprovada como política de negócio e sua orquestração pertence à Central de Manutenções. A central reutiliza a mesma operação sem duplicar regras de carga ou reconciliação.

**Alternativas consideradas**: registrar uma segunda agenda dentro do catálogo foi rejeitado por fragmentar a política operacional. Executar a carga somente na inicialização foi rejeitado por ser imprevisível e não controlável.

## Decisão 5: Singleton com recusa de concorrência

**Decisão**: a sincronização utiliza um bloqueio compartilhado entre a agenda diária e o disparo manual. Enquanto houver carga em andamento, a nova solicitação não espera nem cria outra instância: ela é recusada. Recusas manuais são registradas na auditoria administrativa.

**Racional**: a carga completa reconcilia o mesmo catálogo global. Executá-la em paralelo não aumenta a disponibilidade e pode produzir trabalho duplicado ou observabilidade ambígua.

**Alternativas consideradas**: fila de espera e múltiplas instâncias foram rejeitadas para esta rotina, pois não são necessárias para uma atualização diária e acrescentariam estados operacionais sem benefício.

## Decisão 5: Log operacional sem histórico de cargas

**Decisão**: Registrar resultado e falhas da atualização apenas no canal de log operacional; não criar tabela de execução, payload bruto ou trilha de auditoria da carga.

**Racional**: atende à decisão de produto de evitar tabelas de rastreabilidade para manutenção, preservando observabilidade suficiente para operação e testes.

**Alternativas consideradas**: tabelas de lote, itens de carga ou snapshots foram rejeitadas por ampliarem o escopo sem necessidade atual.
