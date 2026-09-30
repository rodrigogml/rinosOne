# Interface Checklist: Interface unificada de acessos e permissões

**Purpose**: Validar a completude e a coerência dos requisitos de superfície humana antes da decomposição em tarefas.
**Created**: 2026-09-28
**Feature**: [spec.md](../spec.md)

## Cobertura e inventário

- [x] CHK001 - Todas as superfícies humanas declaradas possuem cobertura explícita e itens `INT-*` para o escopo `FULL` ou `PARTIAL`? [Completude, Interface §Interface Coverage e §Interaction Inventory] {auto} — três superfícies têm cobertura e inventário correspondente.
- [x] CHK002 - As diferenças entre administração, compartilhamento e entrada na casca estão documentadas sem prometer paridade indevida? [Consistência, Spec §Cobertura de superfícies; Interface §Interface Coverage] {auto} — a casca é `PARTIAL`; as demais têm responsabilidades distintas.
- [x] CHK003 - Interfaces existentes modificadas identificam componente, ponto de entrada e comportamento atual? [Rastreabilidade, Interface §Current-State Evidence] {auto} — há evidência para Administração, Drive e Área de Trabalho.
- [x] CHK004 - Cada interação mapeia para história, requisito funcional, critério de sucesso e contrato? [Rastreabilidade, Interface §Traceability] {auto} — todas as três linhas possuem os quatro vínculos.

## Estados, responsividade e feedback

- [x] CHK005 - Cada interação define os estados inicial, carregamento, vazio, pronto, processamento, sucesso, erro de validação, erro remoto, offline, acesso negado e dado desatualizado? [Cobertura, Interface §INT-WEB-ADMIN-001 a §INT-WEB-ACCESS-001] {auto} — estados não aplicáveis são justificados na navegação sem formulário.
- [x] CHK006 - Confirmações de operações de acesso identificam de forma inequívoca alvo, contexto, efeito e vigência? [Clareza, Spec FR-API-012; Interface §INT-WEB-ADMIN-001 e §INT-WEB-SHARING-001] {auto} — ambos os fluxos descrevem esses quatro elementos.
- [x] CHK007 - Conflito, revogação concorrente e mudança de contexto definem preservação segura de dados e nova confirmação? [Cobertura, Spec FR-API-017; Plan §Desenho técnico; Interface §INT-WEB-ADMIN-001] {auto} — o contrato usa `409`/`412` e reconsulta contextual.
- [x] CHK008 - Desktop, tablet e telefone possuem comportamento de reflow, navegação e métodos de entrada explicitamente definidos? [Cobertura, Interface §INT-WEB-ADMIN-001 e §INT-WEB-SHARING-001] {auto} — painel/duas colunas, overlay e página móvel são especificados.

## Acessibilidade, conteúdo e wireframes

- [x] CHK009 - Foco, teclado, leitor de tela, regiões vivas, contraste textual, toque e movimento reduzido estão definidos para os fluxos críticos? [Constitution Alignment, Interface §Cross-Surface Rules e detalhes `Accessibility`] {auto} — requisitos aparecem por interação e na regra transversal.
- [x] CHK010 - Terminologia de workspace evita posse individual de arquivo/pasta e usa rótulos consistentes entre superfícies? [Consistência, Spec §Limites e decisões de produto; Interface §Shared Content and Terminology] {auto} — “responsável pelo workspace” é o termo canônico.
- [x] CHK011 - Localização, expansão de texto, datas e timezone estão definidos para os idiomas suportados? [Cobertura, Interface detalhes `Localization`] {auto} — os três itens declaram Vue I18n; datas e vigência são localizadas quando aplicáveis.
- [x] CHK012 - Wireframes obrigatórios existem e declaram que o texto da interface é a fonte de verdade? [Completude, Interface §Wireframes; wireframes/int-web-admin-001.md; wireframes/int-web-sharing-001.md] {auto} — os dois artefatos de baixa fidelidade estão presentes.

## Contratos e componentes

- [x] CHK013 - A interface delega autorização final à API e usa capabilities somente para composição visual? [Segurança, Spec §Limites e decisões de produto; Plan §Desenho técnico; Interface §INT-WEB-ADMIN-001] {auto} — a regra está explícita em todos os níveis.
- [x] CHK014 - Componentes novos e reutilizados são delimitados por módulos reais, sem uma segunda arquitetura de navegação? [Consistência, Plan §Estrutura do projeto; Interface detalhes `Components and Design System`] {auto} — módulos `authorization`, `drive`, `workspace` e design system são reutilizados.
- [x] CHK015 - Dados em cache, estado desatualizado e falhas remotas têm comportamento de UI definido? [Cobertura, Interface detalhes `Integration and Contracts` e estados `partial-stale`] {auto} — todas as interações determinam revalidação ou bloqueio de escrita.

## Notes

- Itens `{auto}` foram resolvidos com evidência citada.
- Não há itens `{humano}` ou gaps de requisitos abertos neste domínio.
