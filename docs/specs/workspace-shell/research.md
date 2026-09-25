# Pesquisa Técnica: Área de Trabalho da Aplicação

## Decision 1: Registro efêmero por aba

**Decision**: manter a coleção de superfícies, a superfície ativa, o estado de recolhimento e as sobreposições em memória no cliente, isolados por aba.

**Rationale**: preserva produtividade dentro da mesma aba sem restaurar dados ou contexto possivelmente obsoletos após atualização. Também mantém a regra de contexto de organização já estabelecida.

**Alternatives considered**:

- Persistir em armazenamento do navegador: rejeitado porque conflita com a regra de não restauração desta fase e amplia a chance de reaplicar contexto inválido.
- Gravar na sessão do servidor: rejeitado porque compartilharia estado entre abas e ampliaria a responsabilidade da sessão.

## Decision 2: Catálogo interno de destinos com contrato explícito

**Decision**: integrar cada superfície futura por uma definição declarativa de destino, escopo, ícone, título, política de instância e capacidade de alterações pendentes.

**Rationale**: o shell precisa saber abrir, focar e encerrar superfícies sem importar regras de um módulo de negócio. O catálogo começa vazio, pois nenhum módulo foi aprovado.

**Alternatives considered**:

- Codificar destinos diretamente no menu: rejeitado por duplicar regras e tornar a evolução de módulos inconsistente.
- Criar um catálogo remoto agora: rejeitado porque permissões e produtos ainda não foram especificados.

## Decision 3: Superfície ativa com cache visual local

**Decision**: renderizar somente a superfície ativa, preservando as demais abertas no ciclo de vida visual da mesma aba.

**Rationale**: reduz custo visual e mantém o estado local esperado ao alternar tarefas, sem implementar janelas flutuantes livres.

**Alternatives considered**:

- Renderizar todas as superfícies simultaneamente: rejeitado por custo, conflitos de foco e acessibilidade.
- Destruir a superfície inativa: rejeitado porque perderia o estado local que a barra de tarefas deve preservar.

## Decision 4: Adaptação móvel orientada a uma superfície

**Decision**: em tela estreita, substituir menu lateral, mega menu e barra de tarefas por painéis modais de navegação e de superfícies abertas; a área principal mostra apenas uma superfície.

**Rationale**: entrega paridade de tarefas sem tentar reproduzir densidade de desktop em uma viewport limitada.

**Alternatives considered**:

- Reduzir as janelas de desktop até caberem: rejeitado por comprometer legibilidade e toque.
- Retirar a capacidade de alternar superfícies: rejeitado porque perderia continuidade de trabalho.

## Decision 5: Camada única de sobreposições e fila de feedback

**Decision**: centralizar diálogos, confirmação de descarte e mensagens temporárias no shell, com pilha de diálogos e fila de notificações.

**Rationale**: garante foco, ordem, retorno ao originador e aparência consistente em todas as superfícies futuras.

**Alternatives considered**:

- Cada módulo controlar seus próprios overlays: rejeitado por gerar concorrência de foco, estilos e regras de Escape.
- Usar somente mensagens bloqueantes: rejeitado por interromper fluxos simples desnecessariamente.
