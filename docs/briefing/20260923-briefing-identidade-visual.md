# Briefing da Iniciativa: Identidade Visual e Design System

**Data**: 2026-09-23
**Status**: Confirmado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: a fundação visual reutilizável da plataforma, incluindo identidade, temas, preferências de apresentação, internacionalização e componentes compartilhados.

**Problema que resolve**: evitar interfaces inconsistentes ou componentes isolados, mantendo uma experiência tecnológica, moderna e premium em toda a plataforma.

**Proposta de valor**: dar à pessoa usuária uma interface distinta, acessível e adaptável às suas preferências, enquanto torna a evolução dos produtos e módulos futuros visualmente coerente.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
| --- | --- | --- |
| Visitante | Pessoa sem acesso autenticado | Utilizar as telas públicas de entrada e criação de conta com as preferências visuais escolhidas. |
| Usuário autenticado | Pessoa com acesso à plataforma | Escolher idioma, tema e densidade visual para utilizar a plataforma conforme sua necessidade. |

**Stakeholders de decisão**: responsável pelo produto e identidade visual da plataforma.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
| --- | --- | --- | --- | --- | --- |
| Web responsiva | Visitante e usuário autenticado | Navegadores modernos em desktop, tablet e telefone | MVP | Online | Única interface desta iniciativa |

**Restrições tecnológicas já obrigatórias**: a fundação deve respeitar a interface web existente e permitir evolução futura sem duplicação de padrões visuais.

## 4. Escopo

### MVP (Essencial)

1. Identidade tecnológica, moderna e premium na direção Rubi Industrial, grafite e prata.
2. Temas claro, escuro e acompanhamento da preferência do sistema.
3. Preferências de idioma, tipografia, espaçamento e tamanho de componentes.
4. Português do Brasil, inglês, espanhol e francês como idiomas disponíveis.
5. Tokens centralizados para todos os atributos visuais reutilizáveis.
6. Componentes de interface reutilizáveis e consistentes.
7. Aplicação imediata nas telas de entrada, criação de conta, confirmação por e-mail e área autenticada.
8. Uso dos ativos de marca existentes sem alteração dos originais.

### Pós-MVP (Desejável)

1. Sincronizar as preferências visuais com o perfil da pessoa autenticada quando o perfil existir.

### Fora de Escopo

- Criação de produtos ou módulos de negócio adicionais.
- Alteração da marca, do logotipo ou dos arquivos originais fornecidos.
- Sincronização de preferências com a conta nesta fase.
- Interfaces nativas para dispositivos móveis ou desktop.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: consistência visual e acessibilidade > experiência premium e diferenciada > velocidade de entrega > expansão de escopo.

**Decisões explícitas**:

- Rubi Industrial é a cor de ação principal; grafite e prata formam os neutros, e ciano é reservado a acentos sutis.
- A apresentação premium usará camadas, gradientes, contornos metálicos de baixo contraste, sombras suaves e transições discretas, evitando excesso de transparência ou animações chamativas.
- Tema e densidades serão reunidos em um único popup reutilizável de preferências visuais; idioma permanecerá em um seletor reutilizável separado.
- As preferências locais serão preservadas no navegador sem exigir autenticação.
- Componentes não serão criados como exclusivos de uma tela; toda composição deverá ser reaproveitável.

## 6. Restrições

| Restrição | Valor | Notas |
| --- | --- | --- |
| Identidade | Tecnológica, moderna e premium | Rica em detalhes sem comprometer legibilidade. |
| Temas | Claro e escuro | A preferência do sistema será considerada até existir escolha manual. |
| Tipografia | Inter e Space Grotesk | Inter para textos e controles; Space Grotesk para títulos e destaques. |
| Acessibilidade | WCAG 2.2 nível AA | Aplicável aos dois temas e às escalas de preferência. |
| Preferências visuais | Três níveis por dimensão | Fonte, espaçamento e tamanho de componentes devem ser independentes. |
| Idiomas | pt-BR, en, es, fr | Português do Brasil inicia como idioma padrão. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Interface web | Interface web responsiva existente | Único canal previsto nesta iniciativa. |
| Ativos de marca | Logotipo e ícone fornecidos no repositório | Devem ser copiados para o uso público sem alterar os originais. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Todas as cores, tamanhos, famílias tipográficas, arredondamentos, sombras e espaçamentos devem ser definidos e reutilizados por tokens.
- Os três ajustes de fonte, espaçamento e dimensão dos componentes devem permanecer coerentes em toda a interface.
- Deve haver contraste, foco visível, operação por teclado, zoom e comunicação de estado que não dependa apenas de cor.
- A interface deve respeitar a preferência do sistema para redução de movimento.
- A mudança de idioma deve preservar a rota, a autenticação e, quando possível, a jornada em curso.

## 9. Visão de Futuro

**6 meses**: novos produtos e módulos adotam a fundação visual e os componentes definidos por esta iniciativa.

**12 meses**: preferências visuais podem acompanhar a conta da pessoa usuária entre dispositivos e interfaces futuras mantêm a mesma identidade.

**Riscos conhecidos**:

- Contraste insuficiente em um dos temas ou em escalas ampliadas pode comprometer acessibilidade.
- Excesso de efeitos visuais pode reduzir desempenho ou prejudicar clareza.
- A introdução de traduções pode deixar conteúdos sem equivalência caso não haja governança do catálogo de idiomas.

---

## Itens a Definir

| Item | Dimensão | Impacto |
| --- | --- | --- |
| Valores exatos de paleta, tokens e escalas | Especificação e planejamento | Alto |
| Catálogo inicial de componentes e seus contratos | Especificação e planejamento | Alto |
| Conteúdo e tradução de cada texto existente | Especificação de interface | Médio |

---

**Próximo passo recomendado**: especificar a feature `visual-identity` antes do planejamento técnico e do desenho detalhado das interfaces.
