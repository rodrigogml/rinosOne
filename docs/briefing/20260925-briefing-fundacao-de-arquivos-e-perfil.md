# Briefing da Iniciativa: Fundação de Arquivos e Perfil

**Data**: 2026-09-25
**Status**: Confirmado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: uma fundação única de armazenamento de arquivos para a plataforma, tendo o Perfil do usuário como primeiro consumidor. O perfil permitirá alterar nome e avatar; a fundação atenderá, posteriormente, workspaces pessoais, organizações, documentos, imagens, álbuns, compartilhamento e drive.

**Problema que resolve**: evitar mecanismos isolados para avatar, documentos e arquivos de organização, que tornariam deduplicação, quotas, backup, retenção e recuperação inconsistentes.

**Proposta de valor**: manter uma única fonte de verdade para a identidade lógica, versões, posses, consumo e localização física de qualquer arquivo, permitindo evolução sem migrar consumidores entre sistemas de armazenamento.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
| --- | --- | --- |
| Usuário autenticado | Proprietário pessoal de arquivos | Alterar nome e avatar; no futuro organizar, compartilhar e restaurar arquivos pessoais. |
| Organização | Escopo proprietário de arquivos | No futuro possuir arquivos e documentos de módulos associados ao tenant. |
| Recurso do sistema | Consumidor técnico de arquivo | Manter ativos como avatar fora do drive, com ciclo de vida próprio. |
| Equipe de infraestrutura | Operação de armazenamento | Configurar volumes privados, backups e novos backends. |

**Stakeholders de decisão**: responsável pelo produto, plataforma e infraestrutura.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
| --- | --- | --- | --- | --- | --- |
| Web responsiva — Perfil | Usuário autenticado | Navegadores modernos em desktop, tablet e telefone | MVP | Online | Permite nome e avatar. |
| API interna de arquivos | Consumidores autorizados da plataforma | Backend | MVP | Online | Base única para Perfil e futuras funcionalidades. |
| Workspace, drive, álbuns e compartilhamento externo | Usuários e convidados futuros | Web e futuras interfaces | Pós-MVP | Online | Reutilizam o mesmo modelo, sem outro armazenamento. |

**Restrições tecnológicas já obrigatórias**: MySQL mantém o catálogo e as referências; o primeiro backend é um volume local privado configurado por ambiente; a infraestrutura é responsável pela criptografia em repouso dos volumes e backups.

## 4. Escopo

### MVP (Essencial)

1. Fundação de arquivo lógico, versão, posse e objeto físico deduplicado.
2. Persistência de versões imutáveis e ramificadas, preservando a versão de origem de uma edição.
3. Deduplicação física global por conteúdo idêntico, com consumo lógico contabilizado para cada proprietário.
4. Catálogo de objetos, referências, retenção, lixeira, auditoria de órfãos e limpeza segura.
5. Backend local privado configurável, preparado para novos pontos de montagem sem mover objetos já gravados.
6. Compactação configurável por MIME type e extensão, incluindo reprocessamento retroativo.
7. Metadados extensíveis para fotos e vídeos.
8. Perfil do usuário para alterar nome e avatar, com editor reutilizável de recorte quadrado.
9. Namespace lógico de ativos gerenciados pelo sistema, fora das listagens de drive.

### Pós-MVP (Desejável)

1. Workspace pessoal e de organização, com navegação e organização de arquivos.
2. Geração, armazenamento e entrega de thumbnails para imagens, PDFs e vídeos.
3. Álbuns, drive, compartilhamento externo e controles de acesso próprios para links.
4. Quotas e contratação aplicadas a usuários, tenants, produtos e planos.
5. Seleção automatizada de backend baseada em capacidade e saúde operacional.

### Fora de Escopo

- Interface de drive, álbuns ou navegador de arquivos nesta fase.
- Geração ou entrega de thumbnails nesta fase.
- Interface administrativa para cadastrar backends de armazenamento.
- Preservar o original de uma imagem enviada ao editor de avatar; apenas o resultado quadrado confirmado será mantido.
- Bloqueio de upload por quota nesta fase.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: integridade e recuperabilidade > isolamento e controle de acesso > eficiência de armazenamento > experiência de Perfil > expansão de escopo.

**Decisões explícitas**:

- Um arquivo lógico, suas versões, suas posses e seus objetos físicos são conceitos separados.
- Versões formam uma árvore: duas pessoas podem editar a mesma versão de origem e criar ramificações independentes.
- A posse aponta para a versão atual de cada proprietário. Salvar um arquivo compartilhado cria uma nova posse, sem copiar bytes.
- Objetos físicos idênticos são deduplicados globalmente por hash de conteúdo; a autorização nunca depende de revelar a existência do hash a quem não tem acesso.
- A árvore física usa o hash fragmentado, por exemplo `objects/sha256/ab/cd/<hash>.blob` ou um sufixo técnico de compactação. Nome original, extensão declarada e MIME type pertencem ao catálogo, não ao caminho físico.
- A compactação é uma representação física configurável e reprocessável; checksum lógico, checksum do objeto armazenado e codificação aplicada serão rastreáveis.
- Arquivos são privados por padrão. Acesso autenticado, recursos internos e futuros links externos são concessões de acesso conforme o contexto de uso, e não uma propriedade absoluta do arquivo.
- A infraestrutura provê criptografia em repouso; a aplicação mantém hashes de integridade, autorização, retenção e referências, sem criptografia distinta por proprietário que inviabilize deduplicação global.

## 6. Restrições

| Restrição | Valor | Notas |
| --- | --- | --- |
| Lixeira | 30 dias | Arquivos na lixeira continuam consumindo quota lógica. |
| Limpeza explícita | Irrecuperável para a posse | Limpar a lixeira remove imediatamente a posse do usuário; o objeto ainda respeita retenção técnica. |
| Retenção técnica | Mínimo igual à política de backups | Deve ser configurável por ambiente para assegurar que restaurações de banco encontrem objetos e versões referenciados. |
| Primeiro backend | Volume local privado | Configurado por ambiente; novos backends entram por implantação. |
| Organização física | Hash fragmentado | Não contém nome de proprietário, tenant, arquivo ou pasta lógica. |
| Quota | Calculada desde o início | Workspace, sistema, lixeira e total serão subtotais distintos; não haverá bloqueio inicial. |
| Avatar | Resultado quadrado do editor | Apenas a imagem confirmada é mantida; o original transitório é descartado. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Backend | Laravel e PHP existentes | Implementa serviços, autorização, filas e configuração de arquivos. |
| Banco de dados | MySQL existente | Mantém catálogo, versões, posses, metadados, contabilização e referências de objetos. |
| Interface web | Vue e design system existentes | Expõe Perfil e editor reutilizável em web responsiva. |
| Armazenamento inicial | Volume local privado | Permite homologação e prepara o contrato de múltiplos backends. |
| Criptografia em repouso | Infraestrutura | Mantém deduplicação global sem chaves por proprietário. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Objetos físicos são imutáveis; mudanças de conteúdo, edição ou recodificação produzem outro objeto rastreável.
- Cada objeto físico, versão e posse é verificável pelo catálogo. Rotinas de reconciliação encontram referências quebradas e objetos órfãos sem inferir proprietário a partir do caminho.
- Uma versão somente é elegível a remoção física depois de não possuir referências válidas, expirar retenções de usuário e cumprir a retenção mínima de backup.
- Ativos `system-managed`, como avatar, usam o mesmo catálogo e quota, mas não entram no drive nem são excluíveis por suas ações.
- A troca de avatar é atômica: a nova versão é validada e vinculada antes de a posse anterior ser removida; falhas não podem deixar consumo lógico sem dono.
- Metadados de fotos e vídeos serão vinculados à versão, incluindo quando disponível data/hora, dispositivo, orientação e localização.

**Compliance**: nenhum requisito de comunicação legal específico foi definido nesta fase. Segurança de acesso, integridade, retenção e privacidade técnica devem ser mantidas.

## 9. Visão de Futuro

**6 meses**: Perfil usa a fundação de arquivos; workspaces pessoais e de organização podem adotar o mesmo catálogo, posse, versionamento e contabilização.

**12 meses**: drive, álbuns, thumbnails, compartilhamento externo e múltiplos backends operam sem criar outro modelo de arquivos.

**Riscos conhecidos**:

- Deduplicação global exige autorização rigorosa e não pode expor existência, hash ou proprietários de objetos a quem não tem acesso.
- Reprocessamento retroativo de compactação exige filas idempotentes, limites operacionais e retenção adequada das representações durante a troca.
- Restaurações de banco e armazenamento precisam permanecer coordenadas pela retenção técnica configurada.

---

## Itens a Definir

| Item | Dimensão | Impacto |
| --- | --- | --- |
| Formatos, dimensões e tamanho máximo do avatar | Especificação de Perfil | Alto |
| Algoritmos, limiares e política inicial de compactação | Planejamento de armazenamento | Alto |
| Validade, senha, revogação e permissões de links externos | Especificação de compartilhamento | Alto |
| Critérios de capacidade e saúde para escolher um backend | Operação de armazenamento | Médio |
| Processamento antimalware para arquivos genéricos | Segurança e operação | Alto |

---

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para especificar a fundação de arquivos e o Perfil como features relacionadas, com entregas separáveis.
