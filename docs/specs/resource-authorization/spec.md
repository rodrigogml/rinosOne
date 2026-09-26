# Especificação da Feature: Autorização por Recurso

**Feature**: `resource-authorization`  
**Criada em**: 2026-09-26  
**Status**: Draft

## Direção de Produto

O rinosOne deve permitir que uma decisão de autorização considere um objeto específico, sem transformar identificadores de objetos em permissions. A permission continua representando uma ação de negócio; o recurso responde sobre qual objeto aquela ação pode ocorrer.

Esta feature adiciona relações explícitas entre pessoas ou grupos e recursos para complementar grants de PLATFORM, PERSONAL ou TENANT. Uma relação de recurso nunca atravessa a esfera de pertencimento do objeto e nunca substitui a validação prévia do tenant quando ele for organizacional.

O primeiro caso de uso é o compartilhamento de objetos pessoais, como pastas e arquivos. O mesmo mecanismo deve suportar depois recursos de tenant, como uma conta financeira específica, sem conceder acesso a outros objetos do tenant.

## Cobertura de Interfaces

| Superfície | Tipo | Atores | Cobertura | Comportamento funcional | Comportamento excluído ou adiado |
| --- | --- | --- | --- | --- | --- |
| API protegida da plataforma | Other | Pessoa autenticada e consumidor futuro autorizado | FULL | Decide e filtra acesso sobre recurso identificado. | Contratos públicos específicos e consumidores externos. |
| Workspace autenticado | Web responsiva | Pessoa autenticada | PARTIAL | Mostra somente ações e itens aos quais a pessoa possui acesso atual. | Editor genérico de relações e explicação administrativa. |
| Gestão de compartilhamentos | Web responsiva | Titular ou administrador autorizado | DEFERRED | Nenhuma nesta fase. | Tela para criar, alterar e remover relações de recursos. |

## Cenários de Usuário e Testes

### User Story 1 - Compartilhar uma pasta pessoal (Prioridade: P1)

Como titular de uma pasta pessoal, quero conceder leitura ou edição a outra pessoa para colaborar sem abrir todo o meu espaço pessoal.

**Por que esta prioridade**: o compartilhamento de objetos pessoais exige uma decisão por recurso e não pode ser modelado com roles globais por pasta.

**Teste independente**: a pessoa A compartilha uma pasta com B; B executa somente as ações permitidas nessa pasta e não recebe acesso às outras pastas de A.

**Cenários de aceitação**:

1. **Dado** que uma pasta pessoal é compartilhada comigo para leitura, **quando** tento visualizá-la, **então** obtenho acesso somente àquela pasta.
2. **Dado** que uma pasta pessoal é compartilhada comigo para leitura, **quando** tento alterá-la, **então** a operação é negada.
3. **Dado** que uma pasta pessoal é compartilhada comigo para edição, **quando** tento alterá-la, **então** obtenho acesso conforme a ação permitida.
4. **Dado** que não recebi relação para outra pasta do mesmo titular, **quando** tento acessá-la por seu identificador, **então** a operação é negada sem revelar detalhes indevidos.

---

### User Story 2 - Limitar um grant de tenant a um recurso (Prioridade: P1)

Como responsável por uma organização, quero conceder ou reconhecer acesso a uma conta específica sem permitir automaticamente o mesmo acesso às demais contas do tenant.

**Por que esta prioridade**: permissões de tenant são amplas; relações de recurso permitem aplicar o princípio do menor privilégio onde o domínio exigir.

**Teste independente**: uma pessoa recebe relação para a Conta A no Tenant A e é autorizada somente para a ação compatível na Conta A, não na Conta B nem em outro tenant.

**Cenários de aceitação**:

1. **Dado** que tenho relação compatível com uma conta no Tenant A, **quando** executo a ação autorizada sobre essa conta, **então** a operação é permitida.
2. **Dado** que não tenho relação com outra conta no mesmo tenant, **quando** tento a mesma ação nela, **então** a operação é negada.
3. **Dado** que conheço o identificador de recurso de outro tenant, **quando** tento utilizá-lo no Tenant A, **então** a operação é negada sem confirmar a existência do recurso.

---

### User Story 3 - Revogar uma relação de recurso (Prioridade: P1)

Como titular ou administrador autorizado, quero remover um compartilhamento ou relação para que a pessoa deixe de acessar o recurso imediatamente.

**Por que esta prioridade**: relações de recurso são usadas para limitar acesso sensível; a revogação não pode depender de logout.

**Teste independente**: uma pessoa usa um recurso compartilhado, a relação é removida e a próxima operação sobre o mesmo recurso é negada.

**Cenários de aceitação**:

1. **Dado** que possuo acesso por uma relação de recurso, **quando** a relação é removida ou desativada, **então** minha próxima operação sobre o recurso é negada.
2. **Dado** que uma relação é alterada, criada ou removida, **quando** a alteração é concluída, **então** uma auditoria segura registra a mudança.

---

### User Story 4 - Consultar somente recursos acessíveis (Prioridade: P2)

Como pessoa usuária, quero que uma lista de recursos mostre somente os itens aos quais tenho acesso para não descobrir objetos por tentativa de identificador.

**Por que esta prioridade**: proteger operações individuais sem filtrar listagens ainda permitiria exposição indireta de recursos.

**Teste independente**: uma pessoa com relações para um subconjunto de recursos consulta uma lista e recebe somente esse subconjunto, mesmo que existam outros itens no tenant ou espaço pessoal.

**Cenários de aceitação**:

1. **Dado** que possuo acesso a dois recursos dentre vários compatíveis, **quando** consulto a lista protegida, **então** recebo somente os dois recursos permitidos.
2. **Dado** que minha relação é removida, **quando** consulto novamente a lista, **então** o recurso removido deixa de aparecer.

### Casos de Borda

- Um identificador de recurso conhecido não concede permission nem relação.
- Toda relação TENANT confirma que sujeito, recurso e tenant pertencem ao mesmo contexto antes de produzir efeito.
- Relação inativa, permission inativa, restriction aplicável ou recurso indisponível não concede acesso.
- Mais de uma relação compatível pode ampliar somente as ações previstas para o mesmo recurso; uma relation não se propaga para recurso irmão sem regra explícita do tipo de recurso.
- Remover ou indisponibilizar o recurso torna suas relações ineficazes para decisões futuras, preservando a auditoria.
- O mecanismo não avalia limite por valor, horário, localização ou outros atributos contextuais nesta feature.

## Requisitos

### Requisitos Funcionais

- **FR-RA-001**: O sistema DEVE avaliar permission e recurso separadamente, sem incorporar identificador de recurso à chave de permission.
- **FR-RA-002**: O sistema DEVE permitir registrar relações ativas e auditáveis entre pessoa ou grupo e recurso, usando relation compatível com o tipo de recurso.
- **FR-RA-003**: Cada recurso participante da autorização DEVE declarar sua esfera de pertencimento `PLATFORM`, `PERSONAL` ou `TENANT` e seu tipo de recurso.
- **FR-RA-004**: Uma relação PERSONAL DEVE afetar somente o recurso pessoal identificado e não pode conceder acesso a outros recursos do mesmo titular sem relação aplicável.
- **FR-RA-005**: Uma relação TENANT DEVE receber tenant explícito e validar que recurso, sujeito e relação pertencem ao mesmo tenant antes de influenciar a decisão.
- **FR-RA-006**: O sistema DEVE negar acesso quando uma permission exige relation aplicável e nenhuma relation ativa compatível for encontrada para o recurso avaliado.
- **FR-RA-007**: O sistema DEVE considerar grants por role, relações por recurso e restrictions aplicáveis na ordem canônica do contrato de decisão, respeitando a precedência de restriction explícita.
- **FR-RA-008**: O sistema DEVE permitir que um tipo de recurso defina relations e ações compatíveis, como leitura e edição de pasta, sem conceder actions não declaradas.
- **FR-RA-009**: O sistema DEVE invalidar a contribuição de relação inativa, removida ou associada a recurso indisponível na próxima operação protegida.
- **FR-RA-010**: O sistema DEVE filtrar listagens protegidas para retornar somente recursos autorizados, sem depender de uma decisão individual por item como estratégia principal.
- **FR-RA-011**: O sistema DEVE permitir avaliações em lote para um conjunto de actions e recursos, preservando o mesmo resultado de decisões individuais equivalentes.
- **FR-RA-012**: O sistema DEVE registrar auditoria segura para criação, alteração, ativação, desativação e remoção de relation.
- **FR-RA-013**: O sistema DEVE responder falhas de recurso de forma consistente por operação, sem confirmar a existência de recurso fora do contexto autorizado.
- **FR-RA-014**: O sistema NÃO DEVE introduzir condições contextuais, limites numéricos, delegation ou editor visual genérico de relation nesta feature.
- **FR-RA-015**: A feature NÃO DEVE depender de agendamento, credencial externa, rotação de chaves ou persistência de decisão na sessão.

### Entidades Principais

- **Recurso autorizado**: objeto identificável protegido por uma action e pertencente a uma esfera, como pasta pessoal ou conta de tenant.
- **Tipo de recurso**: categoria que declara quais relations e actions podem ser avaliadas para seus recursos.
- **Relation**: vínculo ativo e auditável entre pessoa ou grupo e recurso, com semântica definida pelo tipo do recurso.
- **Avaliação em lote**: decisão consistente para múltiplos pares de permission e recurso de uma mesma solicitação.
- **Listagem autorizada**: conjunto de recursos filtrado pela mesma política que protege a operação individual.
- **Evento de auditoria de relation**: registro seguro de mudança administrativa sobre uma relation.

## Critérios de Sucesso

### Resultados Mensuráveis

- **SC-RA-001**: 100% dos cenários automatizados com relation para um recurso permitem somente as actions compatíveis naquele recurso.
- **SC-RA-002**: 100% dos cenários automatizados com identificador de recurso de outro tenant resultam em negação segura.
- **SC-RA-003**: 100% dos cenários automatizados de remoção ou desativação de relation negam a próxima operação sem novo login.
- **SC-RA-004**: 100% das listagens protegidas testadas retornam somente recursos autorizados para a pessoa solicitante.
- **SC-RA-005**: 100% das alterações de relation abrangidas pela feature produzem evento de auditoria seguro e não editável.
