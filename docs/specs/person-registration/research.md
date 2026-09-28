# Pesquisa Técnica — Cadastro de Pessoas por Organização

**Data**: 2026-09-28  
**Escopo**: decisões técnicas para o primeiro módulo organizacional de Pessoas.

## Decisão 1: isolamento e identidade

**Decisão**: as tabelas do módulo pertencem ao schema isolado de cada organização. Cada entidade usa identificador `BIGINT UNSIGNED AUTO_INCREMENT`; não haverá coluna `idTenant` nas tabelas do módulo.

**Racional**: a organização já é a fronteira física e de acesso dos seus dados de domínio. Repetir sua identidade em cada registro cria uma segunda fonte de escopo e não melhora o isolamento.

**Alternativas consideradas**: manter Pessoas no catálogo global com escopo por coluna foi rejeitado por permitir compartilhamento não aprovado; adicionar `idTenant` dentro de cada schema foi rejeitado por redundância e risco de divergência.

## Decisão 2: catálogos corporativos como referências unidirecionais

**Decisão**: endereço e conta bancária podem referenciar os catálogos corporativos de País, UF, Município, Localidade e Instituição Financeira. Essas referências seguem a direção organização para catálogo corporativo; a referência é opcional quando o dado pode ser informado textualmente.

**Racional**: a Pessoa precisa utilizar dados já catalogados, mas não pode exigir que uma rua, CEP ou conta tenha sido previamente cadastrada no catálogo. O endereço final continua sendo dado da organização.

**Alternativas consideradas**: copiar os catálogos para cada organização criaria divergência; bloquear endereços sem localidade reconhecida impediria o uso normal do cadastro; permitir país brasileiro em texto livre contrariaria a regra aprovada de vínculo territorial.

## Decisão 3: documentos e normalização

**Decisão**: CPF e CNPJ são opcionais, porém normalizados e únicos por schema quando informados. A validação de tipo impede CPF em PJ e CNPJ em PF. Chaves Pix, contatos telefônicos e e-mails possuem representações normalizadas próprias para validação, busca e prevenção de repetição no mesmo registro de Pessoa.

**Racional**: a ausência de documento é válida para Pessoas ainda não identificadas ou entidades sem documento aplicável; formatações diferentes não podem criar uma segunda identidade dentro da mesma organização.

**Alternativas consideradas**: tornar documento obrigatório impediria os casos aprovados; unicidade global violaria o isolamento organizacional; usar o nome de exibição como identidade impediria homônimos legítimos.

## Decisão 4: relacionamento direcional com semântica inversa

**Decisão**: cada relacionamento é um único registro direcional. O tipo do relacionamento pertence a um conjunto fechado do módulo e conhece seu tipo oposto para apresentação do vínculo a partir da outra Pessoa. `OTHER` é o próprio tipo oposto.

**Racional**: um único registro evita duplicação e inconsistência entre relações recíprocas, preservando a linguagem adequada quando o vínculo é visto por cada ponta.

**Alternativas consideradas**: criar dois registros recíprocos duplicaria estados; usar somente classificações neutras perderia significado para parentesco e vínculos de trabalho; aceitar tipo livre reduziria a capacidade de tratamento pelo sistema.

## Decisão 5: exclusão física segura

**Decisão**: dados filhos exclusivamente pertencentes à Pessoa — endereços, contatos, contas, chaves Pix e relacionamentos em que ela participa — são removidos junto dela. Antes da exclusão, o módulo consulta verificadores explícitos dos demais módulos para comunicar usos conhecidos; uma falha de integridade imprevista permanece como salvaguarda final.

**Racional**: relacionamentos não podem deixar pontas ausentes, mas referências de outros módulos não devem ser apagadas em cascata sem uma decisão daquele módulo. A explicação antecipada reduz erro operacional sem substituir a proteção de integridade.

**Alternativas consideradas**: manter relacionamento com ponta nula contraria o comportamento aprovado; deixar toda a experiência para a falha do banco produz mensagem insuficiente; apagar referências de módulos externos poderia apagar registros de negócio indevidamente.

## Decisão 6: auditoria de operações da Pessoa

**Decisão**: o módulo registra eventos mínimos de criação, alteração, inativação, reativação e exclusão, com ator, data, ação e identificador histórico da Pessoa. O evento não mantém cópia irrestrita de documentos, endereços ou chaves Pix, expira após 90 dias por padrão configurável por ambiente e é removido por rotina diária explícita de Pessoas, observada pelo Hub de Manutenções.

**Racional**: atende à auditoria de manutenção organizacional e reduz a retenção desnecessária de dados pessoais sensíveis. O identificador histórico não bloqueia exclusão física.

**Alternativas consideradas**: depender somente de logs técnicos não oferece consulta funcional de auditoria; persistir snapshots completos amplia exposição de dados pessoais; manter FK obrigatória para a Pessoa impediria a exclusão aprovada.

## Decisão 7: proteção de dados em repouso e apresentação

**Decisão**: a proteção criptográfica em repouso pertence exclusivamente à infraestrutura de banco, armazenamento e backup. O módulo não cifra campos de aplicação e apresenta CPF, CNPJ, conta bancária e chave Pix sem mascaramento; senhas permanecem a única exceção de apresentação mascarada, fora deste módulo.

**Racional**: a organização considera esses identificadores dados particulares, não privados, e quer evitar duplicidade de mecanismos de criptografia ou mascaramento no domínio de negócio.

**Alternativas consideradas**: criptografia por campo e mascaramento por permissão foram rejeitados para esta fase por transferirem à aplicação uma responsabilidade definida para a infraestrutura.

## Decisão 8: política geral de API

**Decisão**: listas usam `page` a partir de 1 e `perPage`, com padrão configurável de 50 e máximo configurável de 200. A ordem padrão precisa ser estável. Requisições mutáveis usam `Idempotency-Key` UUID v4 e mantêm resultado por 24 horas configuráveis. O limite geral autenticado é 120 requisições por minuto por usuário e organização, também configurável por ambiente. O corpo JSON tem limite geral de 1 MiB, configurável por ambiente.

**Racional**: paginação por página é direta para tabelas e cartões; limite de 200 evita transferências excessivas. Idempotência evita duplicação em reenvios, especialmente para Pessoas sem documento, e a limitação de taxa reduz enumeração e abuso sem criar regra particular para este módulo.

**Alternativas consideradas**: cursor como padrão foi rejeitado por complexidade não necessária; limites específicos por endpoint foram adiados até existir evidência de necessidade; última gravação prevalecer foi rejeitada porque sobrescreve alteração alheia sem aviso.

## Decisão 9: concorrência e cache

**Decisão**: atualizações usam versão de leitura; uma versão divergente devolve conflito e exige recarregar antes de alterar novamente. Não há mesclagem automática, manual nem política de última gravação. Listas e detalhes de Pessoas não usam cache compartilhado nesta fase; a paginação, os índices e a leitura sob demanda atendem a meta de desempenho.

**Racional**: bloqueio explícito evita perda silenciosa de dados. Dados pessoais e sua situação devem refletir o banco na próxima leitura autorizada; não há evidência que justifique cache compartilhado.

**Alternativas consideradas**: mesclagem de campos amplia a experiência e o risco; cache compartilhado introduz invalidação e potencial exposição entre contextos; usar somente `updatedAt` sem pré-condição não protege a gravação.

## Decisão 10: contratos e interface

**Decisão**: a API versionada expõe operações do agregado de Pessoa sob o contexto explícito da organização. A web responsiva é a única superfície humana e entrega capacidade integral em desktop, tablet e telefone; detalhes de telas serão definidos em especificação de interface posterior.

**Racional**: preserva a fronteira de domínio e contrato existente, sem criar cliente nativo paralelo e sem antecipar interação de tela no plano técnico.

**Alternativas consideradas**: expor operações sem contexto organizacional enfraqueceria o isolamento; separar desktop e mobile como produtos distintos duplicaria comportamento sem necessidade; detalhar telas neste documento misturaria planejamento técnico e design de interface.
