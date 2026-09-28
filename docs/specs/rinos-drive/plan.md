# Plano técnico — Rinos Drive

## Resumo

O Rinos Drive entrega um navegador privado para os workspaces pessoal e organizacional usando o catálogo de arquivos e a árvore lógica existentes. A implementação cria uma borda HTTP específica, uma projeção segura de itens, ingestão de uploads de workspace e exportações múltiplas efêmeras. Não cria novo backend, linhagem de arquivo, mecanismo de autorização ou sistema de compartilhamento.

## Contexto técnico

- **Backend**: PHP 8.2+, Laravel 12, serviços de domínio, filas persistidas e scheduler.
- **Web**: Vue 3, TypeScript, Pinia, Axios e design system existente.
- **Dados**: MySQL 9 no schema global `rinosone`; arquivos permanecem em backends privados configurados pela instância.
- **Testes**: PHPUnit para domínio, persistência e HTTP; Vitest para parsers, store e superfícies; type-check e build de produção.
- **Restrições**: API JSON versionada, default deny, tenant explícito, IDs numéricos, nenhum caminho físico ou segredo em payload, sem bloqueio de quota nesta fase.
- **Interface Design Applicability**: REQUIRED — há desktop e mobile, navegação hierárquica, seleção, teclado, sobreposições e estados assíncronos.

## Constitution Check

*GATE: aprovado antes do desenho e rechecado após este plano.*

| Princípio | Status | Notas |
| --- | --- | --- |
| I. Simplicidade incremental e escopo autorizado | PASS | Reutiliza a fundação; compartilhamento, previews, edição e quotas bloqueantes permanecem adiados. |
| II. Fronteira API e domínio independente da interface | PASS | Navegador consome projeções e comandos HTTP; regras ficam em serviços de Drive, arquivos e autorização. |
| III. Identidade e acesso seguros por padrão | PASS | Default deny, revalidação por item e nenhuma URL pública são obrigatórios. |
| IV. Dados mínimos, sessões controladas e configuração segura | PASS | Exportação guarda somente manifesto, estado e chave privada; limites e prazo ficam no ambiente. |
| V. Mudanças verificáveis e documentação alinhada | PASS | Contrato, modelo, quickstart, interface e backlog acompanharão testes de cada fronteira. |
| VI. Identidades numéricas e referências unidirecionais | PASS | Nova exportação usa identidade numérica e referências somente para entidades globais. |

## Arquitetura

### Resolução de alvo e autorização

1. Um resolvedor converte o destino pessoal em usuário autenticado e o destino Work em tenant ativo da superfície.
2. O serviço de Drive exige contexto compatível e nunca aceita proprietário fornecido livremente pelo cliente.
3. A navegação de pasta consulta a autorização efetiva antes de projetar árvore, itens, detalhes, download ou alteração.
4. O administrador ativo do tenant é principal integral de seu Drive Work, depois da verificação de restrições aplicáveis.
5. Para membro não administrativo, `READ` e `EDIT` são relations de pasta, diretas ou por grupo, herdadas aos descendentes. `EDIT` inclui `READ`.
6. A lista acessível começa nas raízes concedidas; ancestrais necessários são apenas contexto de breadcrumb e não habilitam enumeração de irmãos.

### Pastas, arquivos e uploads

- Um serviço de projeção lista localização, árvore e lixeira a partir de pastas e posses ativas/recuperáveis, filtradas por autorização.
- Um serviço de comandos aplica criação, renomeação, movimentação e lixeira de modo transacional, delegando o lifecycle existente para as transições de posse.
- A ingestão de workspace valida lote e arquivo, usa staging privado e chama a porta de armazenamento já existente para criar posse `WORKSPACE` no destino autorizado.
- Um resolvedor central de nomes cria sufixos determinísticos em conflito, tanto no upload quanto em criação e movimentação. A resolução e a reserva do nome final ocorrem na mesma transação serializada por workspace e localização de destino, para que operações concorrentes nunca confirmem o mesmo nome lógico.
- Download de item único delega à leitura privada já existente e constrói somente cabeçalhos seguros de entrega.

### Exportação múltipla

- Um comando cria `file_workspaceExport` em `PENDING`, com manifesto de seleção validado e prazo configurado.
- Um job em fila revalida todos os itens e permissões, produz pacote em armazenamento temporário privado e o marca `READY` apenas ao concluir por inteiro.
- O download da exportação revalida sessão, solicitante, contexto e acesso a cada item antes de transmitir o pacote.
- Um job agendado expira registros e bytes em lote pequeno, de modo idempotente e protegido contra concorrência.
- Limites por item, lote, seleção, exportação e área temporária ficam em configuração do ambiente; falhas não deixam pacote parcial disponível.

## Modelo de dados e contratos

- [Modelo de dados](data-model.md) descreve somente `file_workspaceExport`; o restante é reutilizado da fundação.
- [Contrato HTTP](contracts/drive-workspace-api.md) define prefixos pessoais e organizacionais, projeções, comandos, upload e exportação.
- [Pesquisa](research.md) registra as decisões de alvo, autorização, administração, conflitos, temporários e thumbnails.
- [Quickstart](quickstart.md) cobre fluxos pessoais, Work, exportação e roundtrip interface–API.

## Estrutura do projeto

```text
app/
├── Http/Controllers/Api/V1/Drive/       # adaptadores HTTP do Drive
├── Http/Requests/Drive/                 # validação de comandos e uploads
├── Jobs/FileStorage/                    # geração e limpeza de exportação
├── Models/FileStorage/                  # exportação efêmera e relações existentes
├── Services/FileStorage/Drive/          # alvo, projeções, comandos, ingestão e exportação
└── Services/Authorization/              # decisão contextual por pasta e principal administrador
config/file-storage.php                  # limites e retenção temporária por ambiente
database/migrations/core/                # exportações e catálogo/permissões incrementais
resources/js/
├── drive/                               # store, tipos, parser, superfícies e comandos do navegador
├── design-system/                       # controles reutilizáveis de árvore, seleção, toolbar e detalhes
└── workspace/                           # registro dos destinos Pessoal e Work
tests/
├── Feature/                             # HTTP, isolamento, exportação e persistência
├── Unit/                                # nomes, permissões e limites
└── js/drive/                            # superfície, store, atalhos e acessibilidade
docs/specs/rinos-drive/                  # artefatos desta feature
```

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
| --- | --- | --- | --- |
| Tabelas e colunas | `file_*`, `id...`, camelCase | migrations e constraints | `database/migrations/core/` |
| Serviços e DTOs de domínio | PascalCase/camelCase | PHPUnit | `app/Services/FileStorage/Drive/` |
| Payload JSON | camelCase | requests, responses e testes de contrato | `contracts/drive-workspace-api.md` |
| Tipos e parsers web | camelCase | TypeScript, parser e Vitest | `resources/js/drive/` |
| Rotas e parâmetros | caminhos kebab-case; ids numéricos | roteador e requests | `routes/api/authenticated.php` |

**Camada de mapeamento (DB ↔ DTO)**: serviços de Drive consultam modelos e retornam projeções de localização/item/exportação; controllers não expõem modelos ou dados físicos diretamente.

**Validação de schema**: requests são validados no backend; responses são cobertas por testes HTTP e interpretadas por parsers TypeScript antes de atualizar o estado de interface.

**Envelope de erro**: erros previsíveis seguem o envelope seguro da plataforma e não contêm nome de item inacessível, caminho, hash, consulta, stack trace ou estado de permission de terceiro.

## Validação planejada

- Testes de domínio para alvo tipado, relações `READ`/`EDIT`, administrador integral, restrição prevalente, herança e revogação.
- Testes de persistência para exportação, prazo, estados, índices e remoção idempotente de temporários.
- Testes HTTP para árvore, navegação, comandos, upload múltiplo, download, exportação, limites, isolamento pessoal/tenant e negação segura.
- Testes de interface para visualizações, seleção, estado de upload, detalhes, lixeira, teclado, foco, mobile e troca de contexto.
- Roundtrip end-to-end do navegador até o contrato real de upload e listagem, conforme quickstart.

## Complexity Tracking

Nenhuma violação da Constituição foi identificada. A exportação assíncrona é complexidade necessária para não transformar arquivos temporários em acervo do usuário e para manter seleções múltiplas seguras e recuperáveis.
