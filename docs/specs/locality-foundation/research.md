# Pesquisa Técnica — Fundação de Localidades

**Data**: 2026-09-27
**Escopo**: decisões verificadas para a fundação territorial e postal brasileira.

## Decisões

| Tema | Decisão | Fundamentação |
| --- | --- | --- |
| Fonte territorial brasileira | IBGE é a autoridade para UFs e Municípios; a rotina também cria e mantém o País Brasil. | A API de localidades publica códigos e a hierarquia oficial de UFs e Municípios. |
| Países | O catálogo começa com Brasil, criado pela rotina IBGE, mas sua estrutura preserva ISO alpha-2, alpha-3 e numérico para expansão internacional. | Permite referências postais internacionais sem antecipar estados ou municípios estrangeiros. |
| Fonte postal inicial | ViaCEP e BrasilAPI são adaptadores independentes e ativos quando habilitados. | Ambos devolvem dados públicos de CEP e, quando disponível, código IBGE de município; nenhum substitui a autoridade territorial do IBGE. |
| Consulta postal | Primeiro responde somente a base local; em seguida uma tarefa assíncrona consulta todas as fontes habilitadas em paralelo. | A resposta local não depende de rede externa e a consolidação continua mesmo se o consumidor encerrar sua tela. |
| Acompanhamento | O estado efêmero da consulta fica no cache compartilhado, indexado por país e CEP normalizado; não há tabela de auditoria de consultas. | O banco preserva apenas o catálogo e sua proveniência. Cache e fila padrão da aplicação já são persistentes/compartilhados. |
| Duplicidade | Não há estado `MERGED`, redirecionamento nem remoção por similaridade textual. A primeira versão só remove automaticamente quando a equivalência forte for comprovada. | Evita ocultar resultado legítimo em CEPs com dados divergentes ou imprecisos. |
| Remoção | Referência removida preserva origem e assinatura; retorno posterior da mesma identidade não a reativa. | A decisão de qualidade da Plataforma prevalece sobre nova resposta externa até reativação administrativa futura. |
| Agenda IBGE | O agendador verifica a rotina a cada hora. Ela executa se não houver sucesso anterior, se o último sucesso tiver um mês ou mais, ou após falha respeitando atraso configurável de seis horas. | A primeira carga ocorre sem ação humana; falhas são reavaliadas sem causar repetição contínua. O lock singleton recusa concorrência. |

## Fontes consultadas

- [IBGE — documentação da API de localidades](https://servicodados.ibge.gov.br/api/docs/localidades): endpoints oficiais de países, estados e municípios, incluindo os códigos que sustentam a reconciliação territorial.
- [IBGE — endpoint de estados](https://servicodados.ibge.gov.br/api/v1/localidades/estados): publicação atual de `id`, `sigla`, `nome` e região para as UFs.
- [ViaCEP — webservice](https://viacep.com.br/): consulta de CEP e campos como logradouro, bairro, localidade, UF e código IBGE quando informados.
- [BrasilAPI — CEP v2](https://brasilapi.com.br/docs#tag/CEP-v2): contrato público com estado, cidade e, quando disponível, identificadores IBGE de cidade e estado.

## Estratégia de equivalência inicial

Cada resposta é normalizada sem apagar o texto de origem. Uma equivalência entre resultados de provedores só é comprovada quando todos os dados abaixo existem e concordam:

1. país e CEP normalizados;
2. Município IBGE reconciliado;
3. nome de logradouro normalizado, incluindo o tipo quando a fonte o fornecer;
4. bairro normalizado, quando ambos os provedores o fornecerem.

Ausência de um dos dados, conflito territorial ou mera semelhança de texto mantém candidatos distintos e ativos. A identidade externa do provedor é a chave preferencial para reencontrar uma observação já incorporada; quando ela não existir, é usada a assinatura normalizada completa. A regra pode ser estendida por adaptador somente com novos testes de equivalência.

## Resiliência e segurança

- Cada adaptador define tempo limite curto, valida resposta e converte sua saída ao contrato interno; falha de um adaptador não cancela os demais.
- A tarefa de enriquecimento é idempotente por observação de origem e usa lock por `país + CEP` para evitar consultas concorrentes equivalentes.
- O cliente recebe apenas `PENDING`, `COMPLETED` ou `COMPLETED_WITH_ERRORS`; nomes de fontes, exceções, conteúdo bruto, credenciais e detalhes de rede ficam apenas em log estruturado seguro.
- A ausência de um CEP em uma fonte não remove dados locais. O IBGE não remove automaticamente UFs ou Municípios previamente conhecidos por causa de falha, mudança parcial ou ausência na resposta.

## Alternativas rejeitadas

| Alternativa | Motivo para não adotar agora |
| --- | --- |
| Usar CEP como chave do logradouro | Um CEP pode abranger vários logradouros e uma referência postal pode ocorrer em múltiplos CEPs. |
| Definir uma fonte “vencedora” por ordem de resposta | Latência não é qualidade nem identidade; a regra produziria catálogos não determinísticos. |
| Tabela genérica de rotinas de manutenção | Contraria a decisão do Hub: cada integração é explícita e a manutenção não conhece o Hub. |
| Tabela de auditoria para cada carga ou consulta | O Hub já mantém histórico técnico de execução; logs e atributos de proveniência são suficientes para esta feature. |
