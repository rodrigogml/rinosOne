# Pesquisa Técnica: Compatibilidade de Schemas e Guarda Operacional

## Decisão 1: Migrations globais permanecem fora da inicialização web

**Decisão**: a atualização do catálogo global é uma etapa explícita de implantação, executada pela credencial exclusiva de migrations antes da liberação da aplicação.

**Racional**: iniciar migrations em cada instância web cria concorrência entre réplicas, mistura privilégio de DDL com runtime e transforma uma falha de infraestrutura em comportamento imprevisível da aplicação. A guarda de compatibilidade protege contra a falha operacional de publicar o código antes da migration.

**Alternativas consideradas**:
- Executar migrations em todo boot: rejeitada por corrida entre instâncias e privilégio excessivo na web.
- Confiar apenas no procedimento de deploy: rejeitada porque uma falha humana ou uma execução incompleta ainda permite código incompatível ser atendido.
- Bloquear permanentemente até reiniciar: rejeitada porque a recuperação de uma migration bem-sucedida não deve depender de nova ação do usuário.

## Decisão 2: O histórico de migrations é a fonte de verdade de compatibilidade

**Decisão**: comparar o catálogo de migrations disponível na versão executada com o histórico aplicado no schema correspondente; ausência, leitura incompleta ou erro de acesso equivale a incompatibilidade.

**Racional**: o catálogo é versionado junto do código e o histórico já registra a aplicação por schema. A regra não requer uma segunda versão manual que poderia divergir do catálogo real.

**Alternativas consideradas**:
- Manter número de versão em configuração: rejeitada por duplicar a fonte de verdade.
- Usar somente a última migration aplicada: rejeitada porque não identifica lacunas intermediárias.
- Usar checksum de conteúdo de migrations: adiado; migrations versionadas não devem ser alteradas, e a presença integral do catálogo é suficiente para este escopo.

## Decisão 3: Guarda em dois níveis, antes da regra de negócio

**Decisão**: aplicar uma guarda global antes do atendimento HTTP funcional e uma guarda de organização antes da resolução de contexto ou conexão de runtime da organização.

**Racional**: a guarda global protege interface e API de modo uniforme. A segunda defesa evita que uma rota ou serviço futuro que já tenha superado a entrada global execute contra um schema organizacional atrasado.

**Alternativas consideradas**:
- Esconder somente menus: rejeitada porque não protege APIs nem consumidores diretos.
- Verificar apenas nos módulos de negócio: rejeitada por duplicar regras e deixar novas rotas expostas.
- Tratar toda indisponibilidade como global: rejeitada porque reduz a disponibilidade de organizações não afetadas.

## Decisão 4: Atualização de organizações existentes como ciclo operacional separado

**Decisão**: manter o provisionamento inicial e introduzir um ciclo próprio para detectar e atualizar organizações existentes. O ciclo é idempotente, serializado por organização e executado por worker supervisionado com a credencial de provisionamento.

**Racional**: uma organização ativa não pode reutilizar a semântica de criação inicial sem perder sua distinção de estado e histórico. O ciclo próprio permite indisponibilidade individual, repetição segura e recuperação observável.

**Alternativas consideradas**:
- Atualizar todas as organizações durante o deploy: rejeitada por acoplar duração e falhas de muitos schemas à implantação.
- Atualizar sob demanda na primeira requisição do usuário: rejeitada por atrasar a operação do usuário e permitir pico simultâneo.
- Atualizar somente por ação manual: rejeitada porque manteria organizações incompatíveis até intervenção humana.

## Decisão 5: Comunicação pública mínima e código estável

**Decisão**: respostas funcionais bloqueadas usam códigos estáveis distintos para incompatibilidade global e de organização, ambas com status de indisponibilidade temporária. A interface traduz esses códigos e exibe orientação curta, sem detalhes técnicos.

**Racional**: consumidores precisam reconhecer a condição sem interpretar texto; usuários não precisam nem devem receber detalhes de schemas, migrations ou infraestrutura.

**Alternativas consideradas**:
- Devolver erros de banco originais: rejeitada por segurança e falta de estabilidade.
- Usar somente uma mensagem sem código: rejeitada porque dificulta tratamento consistente por clientes.
- Expor a migration pendente ao administrador final: rejeitada porque a recuperação pertence à operação de implantação, não à interface de negócio.
