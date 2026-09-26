# Pesquisa Técnica: Fundação de Tenants

Este documento resolve as decisões técnicas necessárias antes do desenho da fundação de tenants.

## Decision 1: Plano de controle global e schemas isolados

**Decision**: identidade de tenant, vínculos de acesso, estado de provisionamento e auditoria permanecerão no schema principal `rinosone`. Cada tenant ativo terá seu schema isolado `rinosone_{tenantId}`, onde `tenantId` é a PK `BIGINT UNSIGNED` estável. O nome de exibição nunca compõe o nome físico.

**Rationale**: o sistema precisa validar se uma pessoa pode selecionar o tenant antes de abrir seus dados. O identificador numérico imutável reduz o custo de índices e relacionamentos, evita renomeações operacionais e impede que entrada de usuário seja usada como identificador de banco.

**Alternatives considered**: um único schema com coluna de tenant foi rejeitado por reduzir o isolamento físico. Um schema derivado do nome da empresa foi rejeitado porque o nome é mutável. Guardar vínculos e disponibilidade dentro de cada tenant foi rejeitado porque a autorização deixaria de funcionar durante criação, falha ou indisponibilidade do schema.

## Decision 2: Contexto em memória por aba e tenant explícito na fronteira

**Decision**: o tenant ativo existirá somente no estado de execução da aba atual; não será gravado em cookie, sessão server-side, armazenamento persistente do navegador ou identidade autenticada. A interface solicitará uma validação explícita ao selecionar um tenant. Chamadas contextuais futuras carregarão o `tenantId` no caminho e o backend o validará em toda operação.

**Rationale**: memória da aba é isolada entre abas e se perde em atualização ou restauração do navegador, cumprindo a política de nunca restaurar implicitamente um contexto de tenant. A URL pode identificar um destino contextual, mas não é suficiente para ativá-lo; sem seleção validada, a aplicação permanece no modo pessoal.

**Alternatives considered**: guardar o último tenant na sessão ou no armazenamento local foi rejeitado porque mudaria todas as abas ou restauraria contexto sem ação consciente. Usar apenas um cabeçalho controlado pela interface foi rejeitado porque rotas, integrações e logs perderiam uma identificação verificável e favoritada do escopo.

## Decision 3: Preparação assíncrona, idempotente e com privilégios separados

**Decision**: a criação persiste o tenant, sua membership inicial, a atribuição administrativa protegida e uma operação de provisionamento na mesma confirmação global. Depois da confirmação, um trabalho assíncrono cria o schema, aplica as migrations de tenant e só então promove o tenant a `ACTIVE`. Reenvios da mesma criação usam uma chave de idempotência vinculada ao criador.

**Rationale**: criar schema e aplicar migrations não deve prolongar a requisição da interface nem deixar tenant ativo antes de sua estrutura existir. A fila persistida já disponível no projeto permite retentativas sem duplicar a organização. A credencial de provisionamento permanece distinta da credencial normal de execução.

**Alternatives considered**: criar o schema diretamente na requisição foi rejeitado por timeout, duplicação e recuperação difícil. Conceder permissão ampla de criação de schema à conexão normal foi rejeitado por ampliar o impacto de uma falha da aplicação. Uma ferramenta externa obrigatória foi adiada por introduzir infraestrutura sem necessidade nesta fase.

## Decision 4: Catálogos de migration separados sem reescrever histórico

**Decision**: as migrations já existentes em `database/migrations/` continuam intocadas como histórico do schema principal. Alterações globais futuras desta feature serão adicionadas em `database/migrations/core/`; migrations de tenant ficarão em `database/migrations/tenant/`. Comandos próprios coordenarão esses catálogos e cada schema de tenant manterá seu histórico de migrations independente.

**Rationale**: mover migrations já executadas pode fazer ambientes existentes perderem a associação entre arquivo e histórico aplicado. A separação a partir desta feature preserva a instalação atual e prepara atualização independente dos tenants.

**Alternatives considered**: manter tudo no catálogo padrão foi rejeitado porque não permite aplicar mudanças uma vez por tenant. Copiar migrations globais para cada tenant foi rejeitado porque duplicaria identidade e acesso. Criar um schema de tenant sem catálogo de migrations foi rejeitado porque bloquearia evolução segura dos módulos futuros.

## Decision 5: Associação mínima, sem antecipar gestão de acesso

**Decision**: a fundação criará uma associação ativa para o criador e lhe atribuirá diretamente a role protegida `tenant.administrator`. A validação contextual será feita contra a membership; decisões de capacidade usam a role e permission aplicáveis. Convites, reativação de membros, grupos e catálogo de módulos continuam adiados.

**Rationale**: o vínculo é indispensável para impedir seleção por conhecimento de identificador, mas uma gestão completa de acesso ampliaria indevidamente a fase atual.

**Alternatives considered**: conceder acesso ao tenant apenas pela identidade de quem o criou foi rejeitado porque não preserva o modelo necessário para uma futura associação. Implementar papéis e permissões agora foi rejeitado por não haver módulos que exijam essas decisões.
