# Briefing: Central de Manutenções do Sistema

**Data**: 2026-09-26  
**Status**: Aprovado  
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: uma área administrativa central para concentrar a visualização, o acompanhamento, o disparo e a auditoria das manutenções do sistema.

**Problema que resolve**: cada manutenção não deve criar sua própria tela, agenda ou mecanismo administrativo isolado, porém suas regras de negócio e de execução não podem ser generalizadas artificialmente.

**Proposta de valor**: administradores da Plataforma encontram e controlam as funções de manutenção em um único local, sem retirar de cada rotina a propriedade sobre suas próprias definições.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações Principais |
|------|-------|-----------------|
| Administrador da Plataforma | Operador autorizado | Consulta rotinas, status, relatórios, logs e histórico; altera ou aciona somente as capacidades permitidas pela rotina. |
| Rotina de manutenção | Capacidade técnica integrada | Mantém seu motor, regras, agenda/eventos, concorrência, parâmetros e permissões; é chamada pelo hub quando aplicável. |

**Stakeholders de decisão**: administração da Plataforma e responsável pelo produto.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade Esperada |
|-----------------|----------|--------------------------|-----------|---------------|-------------------|
| Área web administrativa | Administradores da Plataforma | Navegadores modernos em desktop, tablet e telefone | MVP | Online | Responsiva; concentra acesso sem substituir telas específicas futuras. |

**Restrições tecnológicas já obrigatórias**: monólito Laravel/PHP, Vue 3/TypeScript, API JSON versionada, MySQL e a área autenticada existente. Cada integração no hub será implementada especificamente; não haverá interface genérica que uma rotina deva implementar.

## 4. Escopo

### MVP (Essencial)

1. Exibir as rotinas de manutenção conhecidas pelo hub e seus estados, históricos, relatórios e logs técnicos permitidos.
2. Permitir disparo manual, controle de agenda e demais ações somente quando definidos pela rotina específica.
3. Persistir auditoria administrativa separada do histórico técnico de execução, incluindo autor, instante, ação e parâmetros seguros.
4. Manter histórico e logs por 90 dias por padrão, com retenção configurável no arquivo de configuração do sistema.
5. Integrar a atualização diária de instituições financeiras como primeira rotina revisada.

### Pós-MVP (Desejável)

1. Integrar e revisar progressivamente as demais rotinas existentes, inclusive o descarte de autenticações vencidas.
2. Conciliar a autorização de cada rotina com o modelo definitivo de permissões do sistema.

### Fora de Escopo

- Motor genérico, contrato obrigatório ou cadastro dinâmico que padronize artificialmente as rotinas.
- Políticas globais obrigatórias de concorrência, singleton, instâncias, agenda, eventos, retentativas ou parâmetros.
- Dependência de uma rotina de manutenção em relação ao hub.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: regras próprias das rotinas > auditoria e segurança operacional > visibilidade administrativa > conveniência de centralização.

**Decisões explícitas**:

- O hub conhece cada rotina por implementação específica; a rotina não conhece o hub.
- O hub apresenta e solicita ações. Cada rotina permanece dona de seu motor de execução e de seus requisitos.
- Agenda pode ser por horário, evento ou inexistente, conforme a rotina. A tela somente expõe o que foi autorizado pela respectiva definição.
- Retentativas não são uma política do hub; pertencem à rotina.

## 6. Restrições

| Restrição | Valor | Notas |
|-----------|-------|-------|
| Prazo | Não definido | Evolução por rotinas revisadas. |
| Equipe | Não definida | Não informada. |
| Budget | Não definido | Não informado. |
| Técnica | Integração específica por rotina | Não criar abstração ou configuração comum prematura. |
| Retenção | 90 dias | Valor padrão configurável por arquivo de configuração do sistema. |
| Autorização | Por rotina e ação | Integração definitiva depende da fundação de permissões em evolução. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
|--------|-----------|---------------|
| Backend | Laravel/PHP | Stack existente; hub chama os motores específicos. |
| Interface administrativa | Vue 3 e TypeScript | Superfície web autenticada existente. |
| Banco de dados | MySQL | Histórico técnico e auditoria administrativa persistidos. |
| Integrações | Implementações internas específicas | Evita contrato genérico e dependência inversa. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Toda ação administrativa precisa de auditoria persistida, segura e imutável até sua expiração, separada do log técnico da execução.
- Registros técnicos e históricos observam a retenção padrão de 90 dias configurável por ambiente.
- Cada rotina só expõe ações e informações que seus próprios requisitos autorizem.
- O hub não deve iniciar uma rotina automaticamente fora da agenda, evento ou ação manual definidos para ela.

**Compliance**: auditoria operacional e proteção de dados mínimos; requisitos específicos adicionais não foram definidos.

## 9. Visão de Futuro

**6 meses**: as rotinas já existentes estarão revisadas e integradas progressivamente na central, incluindo a atualização diária das instituições financeiras e a limpeza de autenticações vencidas.

**12 meses**: novas manutenções possuirão integração específica na central, com autorização consolidada pelo modelo definitivo de permissões.

**Riscos conhecidos**:

- A fundação de permissões ainda não está fechada, portanto chaves e vínculos definitivos de autorização não podem ser antecipados.
- Integrações específicas podem exigir componentes de interface distintos; isso é um trade-off consciente para respeitar cada rotina.

---

## Itens a Definir

| Item | Dimensão | Impacto |
|------|----------|---------|
| Modelo definitivo de permissões e chaves por ação | Segurança e autorização | Alto |
| Rotinas existentes a migrar, seus requisitos e ordem de revisão | Escopo | Alto |
| Campos técnicos e parâmetros seguros visíveis em cada integração | Interface e segurança | Médio |

---

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para especificar a Central de Manutenções.
