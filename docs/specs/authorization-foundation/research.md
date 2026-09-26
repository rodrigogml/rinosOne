# Pesquisa Técnica: Fundação de Autorização

## Decisão 1: Autorização centralizada no schema global

**Decisão**: permissions, roles, grupos, assignments, decisões e auditoria pertencem ao schema global. Dados de domínio continuam pertencendo ao schema de cada tenant ou ao módulo pessoal que os possui.

**Racional**: o motor precisa avaliar a mesma pessoa em tenants distintos sem o core depender de FKs para dados que vivem em schemas de tenant. Referências a recursos específicos ficam para a feature posterior de autorização por recurso.

**Alternativas consideradas**: manter roles e grupos em cada schema de tenant foi rejeitado porque duplicaria catálogo, dificultaria a decisão de plataforma e criaria lógica de conexão antes da autorização.

## Decisão 2: Membership é elegibilidade; role é concessão

**Decisão**: `tenantMembership` registra somente o vínculo ativo que habilita o contexto organizacional. Toda capacidade é concedida por role e permission. A role protegida `tenant.administrator` é atribuída diretamente ao criador e deve conter todas as permissions TENANT, inclusive as registradas futuramente.

**Racional**: elimina a via paralela de autorização antes chamada OWNER, preserva default deny e permite explicar, auditar e revogar todo acesso pelo mesmo mecanismo.

**Alternativas consideradas**: manter uma flag especial de proprietário foi rejeitado porque duplicaria precedência e exigiria definir, a cada permission nova, se a flag a contorna.

## Decisão 3: Resolução por consulta direta inicialmente

**Decisão**: cada operação protegida consulta o estado atual de memberships, assignments, grupos e roles. Nenhuma lista integral de permissions é persistida na sessão; cache e versionamento de política ficam para `authorization-performance`.

**Racional**: a próxima operação precisa refletir revogações imediatamente e a fundação ainda não possui evidência de carga que justifique cache distribuído.

**Alternativas consideradas**: gravar capabilities no login foi rejeitado porque atrasaria revogações; introduzir cache agora foi rejeitado por antecipar a feature de escala.

## Decisão 4: Hierarquia de grupos por relações tipadas

**Decisão**: grupos receberão pessoas por uma relação própria e outros grupos por outra relação própria. O serviço de domínio verificará alcance antes de criar uma relação entre grupos e recusará ciclos na mesma transação.

**Racional**: FKs claras impedem membros inexistentes e separam relações de pessoa e grupo sem identificadores polimórficos ambíguos.

**Alternativas consideradas**: uma tabela única com tipo de sujeito e identificador polimórfico foi rejeitada porque não preserva integridade referencial completa.

## Decisão 5: Auditoria append-only no domínio de autorização

**Decisão**: alterações administrativas serão registradas em evento de auditoria criado na mesma transação da mudança. O aplicativo não expõe atualização ou remoção desses eventos; conteúdo sensível não entra no payload. Uma rotina diária remove eventos que excederem a retenção de 90 dias por padrão, configurável por ambiente.

**Racional**: um log separado e posterior pode divergir da alteração de segurança que deveria explicar. A imutabilidade aplicacional preserva a trilha sem introduzir infraestrutura externa nesta fase.

**Alternativas consideradas**: reutilizar somente logs técnicos foi rejeitado porque eles não oferecem contrato de retenção, antes/depois ou consulta administrativa futura.

## Decisão 7: Bootstrap de administrador de plataforma

**Decisão**: o primeiro assignment de uma role PLATFORM é inserido diretamente no banco exclusivamente pela equipe de infraestrutura. Cadastro, autenticação e criação de tenant não criam nem promovem administradores de plataforma.

**Racional**: evita uma rota pública ou um fluxo de produto com privilégio de plataforma implícito e mantém a inicialização sob o controle operacional que já possui acesso ao banco.

**Alternativas consideradas**: promover o primeiro usuário cadastrado foi rejeitado por criar escalonamento de privilégio automático; disponibilizar endpoint de bootstrap foi rejeitado por ampliar a superfície administrativa antes da feature de administração.

## Decisão 6: Capabilities são projeções de UX

**Decisão**: a web recebe apenas booleans ou chaves de capacidade necessários para o destino ativo; o backend executa novamente a decisão antes de cada operação.

**Racional**: reduz exposição desnecessária de grants e impede que menu, botão ou estado do browser se tornem autoridade.

**Alternativas consideradas**: enviar todas as permissions efetivas no login foi rejeitado pela política de revogação imediata e por ampliar o payload de sessão.
