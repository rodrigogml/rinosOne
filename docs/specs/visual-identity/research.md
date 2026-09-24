# Pesquisa Técnica: Identidade Visual e Preferências de Interface

## Decision 1: Arquitetura de tokens e temas

**Decision**: estruturar os estilos em três camadas: tokens primitivos (valores brutos), tokens semânticos (papel visual) e tokens de componente. Os temas claro e escuro redefinem somente os tokens semânticos; componentes nunca referenciam cores, espaçamentos ou raios brutos.

**Rationale**: a separação permite trocar tema e preferências globais sem duplicar estilos ou criar divergência entre telas. Os multiplicadores de densidade serão aplicados nas variáveis raiz, de modo que tamanhos derivados preservem proporção.

**Alternatives considered**: manter classes utilitárias com valores diretos em cada tela foi rejeitado por não oferecer a centralização e a consistência exigidas. Duplicar uma folha completa por tema foi rejeitado por aumentar manutenção e risco de divergência.

## Decision 2: Famílias cromáticas e acessibilidade

**Decision**: manter dez famílias cromáticas em tokens, todas com variante clara e escura, usando Rubi Industrial `#AA2643` como ação padrão. O tema escuro usa base grafite/carvão e trama neutra de fibra de carbono, sem influência tonal da ação. A seleção de outra família será introduzida apenas com o perfil de usuário.

**Rationale**: Rubi Industrial diferencia a identidade sem perder contraste com conteúdo claro; a base neutra respeita o acabamento metálico da marca. WCAG 2.2 define contraste mínimo de 4,5:1 para texto normal e permite que animações por interação sejam desativadas; esta decisão preserva ambos os requisitos. [WCAG 2.2](https://www.w3.org/TR/wcag/) e [técnica de redução de movimento](https://www.w3.org/WAI/WCAG22/Techniques/css/C39).

**Alternatives considered**: verde-esmeralda e violeta foram avaliados como direções possíveis de marca, mas descartados pela direção visual aprovada. Usar prata como cor principal foi rejeitado por contraste e reconhecimento de ação insuficientes.

## Decision 3: Tipografia e ativos de marca

**Decision**: carregar localmente as famílias variáveis Inter e Space Grotesk a partir de dependências versionadas. Inter atenderá conteúdo, formulários e controles; Space Grotesk será reservada para títulos e destaques. Copiar o logotipo e ícone originais para os ativos públicos, produzindo variantes dimensionadas de uso sem modificar os arquivos de origem.

**Rationale**: fontes locais evitam uma dependência de rede no primeiro carregamento e preservam a aparência prevista. As famílias aprovadas possuem pesos variados e cobertura adequada aos quatro idiomas iniciais. Variantes de ativos reduzem transferência sem tocar na fonte original.

**Alternatives considered**: fontes remotas foram rejeitadas por adicionarem uma dependência de disponibilidade e privacidade. Usar uma única família foi rejeitado por não entregar a hierarquia tecnológica premium aprovada.

## Decision 4: Internacionalização e fallback de conteúdo

**Decision**: usar Vue I18n 11 em modo Composition API, com catálogos tipados para `pt-BR`, `en`, `es` e `fr`, e `pt-BR` como fallback explícito. O idioma atual atualizará o documento, os controles e os textos em memória; a preferência será salva no navegador apenas depois de validada.

**Rationale**: o Composer da Composition API expõe o idioma ativo e o fallback, e permite a troca global de idioma sem reconstruir a jornada. A documentação oficial descreve `locale`, `fallbackLocale` e a Composition API como mecanismos próprios para este uso. [Vue I18n Composition API](https://vue-i18n.intlify.dev/guide/advanced/composition) e [fallback de idiomas](https://vue-i18n.intlify.dev/guide/essentials/fallback).

**Alternatives considered**: textos condicionais espalhados pelos componentes foram rejeitados por impedir cobertura de catálogo e reaproveitamento. Recarga completa obrigatória foi rejeitada porque pode descartar entrada segura durante uma jornada; a atualização reativa atende o requisito de apresentar imediatamente a interface no novo idioma sem a perda de contexto.

## Decision 5: Preferências locais e prevenção de flash visual

**Decision**: criar uma única preferência visual local, com versão e validação, para tema, idioma, escala de fonte, espaçamento e dimensão de componentes. Um pequeno script inicial no documento aplica tema, idioma e atributos de densidade antes da montagem da aplicação; o estado reativo assume o controle após a montagem.

**Rationale**: uma chave única e versionada elimina estados parciais, permite descarte seguro de valores inválidos e evita que o tema salvo apareça somente após o primeiro desenho da tela. Dados de formulário, senhas, códigos e tokens não participam dessa persistência.

**Alternatives considered**: uma chave por preferência foi rejeitada por facilitar configurações inconsistentes e migrações frágeis. Persistir dados de jornada junto às preferências foi rejeitado por segurança e por estar fora de escopo.

## Decision 6: Organização de componentes reutilizáveis

**Decision**: introduzir um módulo de design system com componentes base para ação, campo, cartão, alerta, ícone, seletor de idioma e popup unificado de preferências visuais. O popup reúne tema, escala de fonte, espaçamento e tamanho de componentes em quatro linhas de botões selecionáveis. As telas de acesso passam a compor esses elementos e não criarão variações locais sem uma necessidade documentada.

**Rationale**: a composição preserva aparência e comportamento consistentes entre entrada, cadastro, confirmação e área autenticada, ao mesmo tempo que evita antecipar módulos de negócio não aprovados.

**Alternatives considered**: manter toda a tela em um único componente foi rejeitado por acoplar identidade visual e regras de acesso. Criar uma biblioteca externa separada foi rejeitado por complexidade prematura para uma única aplicação.
