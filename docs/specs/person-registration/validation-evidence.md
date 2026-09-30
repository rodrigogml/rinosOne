# Evidências de validação — Cadastro de Pessoas por Organização

Este registro preserva os comandos e resultados reproduzíveis da validação automatizada da feature. Ele não substitui a inspeção visual humana prevista na tarefa 5.2.5.

> [!NOTE]
> A massa de desempenho usa identidades deliberadamente sintéticas (`Pessoa sintética NNNNNN`) e não contém documentos, contas, chaves Pix, endereços ou contatos reais.

## Cobertura P1 e P2

| Prioridade | Cenários cobertos | Evidência automatizada |
|---|---|---|
| P1 | Cadastro/localização, endereço/contato e ciclo de vida | Testes de domínio, integração de API, componentes e contrato de Pessoas |
| P2 | Conta bancária/Pix, relacionamentos e duplicação | Testes de serviço, contrato, componentes e rollback atômico do agregado |

Os cenários P1/P2 são executados pelo conjunto completo de testes PHP e pela suíte de interface. A validação mais recente retornou:

```text
php artisan test
503 passed, 2 skipped (2082 assertions)

npm test
45 files passed, 231 tests passed

npm run type-check
passed

npm run build
passed

npm run test:e2e
48 passed
```

## Responsividade, acessibilidade e estados de interface

`application.spec.ts` executa os seis fluxos de Pessoas — catálogo, formulário e dados relacionados, cadastro rápido, duplicação, ciclo de vida e abertura de cadastro — nos viewports desktop (1440×900), tablet (900×1024) e telefone (390×844). Cada viewport também valida a ausência de ações de escrita para uma pessoa usuária somente-leitura.

Os três cenários de revisão visual geram capturas do catálogo, do painel lateral/sheet de filtros e do formulário já carregado nos mesmos viewports, sem versionar as imagens.

`focusTrap.spec.ts` confirma o ciclo de `Tab` e `Shift+Tab` nos diálogos e sheets. Os componentes de Pessoas usam regiões `status`/`alert`, preservam foco de retorno e aplicam área segura, altura dinâmica e campos de ao menos 16px em telefone. A suíte E2E geral valida o contraste mínimo de 4,5:1 dos tokens de texto, ação e erro, o modo de movimento reduzido e a ausência de overflow horizontal nas apresentações extremas.

Os componentes cobrem os estados de carregamento, vazio, pronto, processando, sucesso, erro de validação, erro remoto, offline, acesso negado e dado possivelmente desatualizado quando há uma falha depois de uma leitura válida.

> [!NOTE]
> A validação automatizada não substitui a inspeção visual humana de contraste e aderência aos wireframes; essa é a pendência deliberada da tarefa 5.2.5.

## Segurança e contratos

Os testes `PersonApiAuthenticationTest`, `PersonApiErrorContractTest`, `PersonRouteContractTest` e `ApiPolicyMiddlewareTest` verificam respostas seguras para autenticação, autorização, rotas inexistentes, validação e conflitos, sem revelar identificadores externos ou detalhes internos.

`PersonAggregateServiceTest` confirma rollback da Pessoa e de suas coleções quando uma coleção é inválida. A telemetria de Pessoas armazena somente operação, família de status, contadores e buckets de latência; não armazena tenant, usuário, parâmetros de rota, texto de busca ou atributos de Pessoa.

## Desempenho e operação

`PersonQueryPerformanceBenchmarkTest` prepara 100.000 Pessoas sintéticas em banco temporário, executa 25 consultas concorrentes em processos separados e exige p95 menor ou igual ao limite configurável de 2.000 ms para uma página de 50 itens. A execução local passou.

> [!IMPORTANT]
> Este benchmark é uma proteção de regressão local em SQLite. A aferição de capacidade de produção deve ser repetida no MySQL e na topologia de destino antes da liberação operacional.

`PersonAuditRetentionMaintenanceServiceTest`, `PersonAuditRetentionServiceTest` e `MaintenanceHubServiceTest` validam a retenção diária configurável, seu histórico de execução seguro no Hub e a recusa de execução simultânea.

## Aceite humano e evolução visual futura

A inspeção humana confirmou que os fluxos de Pessoas estão disponíveis e funcionais. O aceite desta feature é restrito ao escopo funcional, responsivo e de navegação definido neste SDD.

> [!IMPORTANT]
> A apresentação visual atual não constitui o padrão definitivo de UI. Os layouts, a composição visual e os componentes de Pessoas serão revistos em um SDD próprio de interface, sem reabrir o escopo funcional deste cadastro.

### Roteiro de aceite visual

Execute `npm run test:e2e -- --grep "captures People visual review"` para gerar os artefatos locais não versionados em `test-results/`. Para cada viewport, conferir o catálogo, os filtros e o formulário:

| Item | Desktop e tablet largo | Telefone |
| --- | --- | --- |
| Catálogo | Tabela, organização atual, busca, ações explícitas e paginação. | Cards legíveis, sem dependência de hover e sem overflow horizontal. |
| Filtros | Painel lateral não modal, sem comprimir a tabela indevidamente. | Sheet de tela cheia, foco e retorno ao acionador ao fechar. |
| Formulário | Seções navegáveis, campos em até duas colunas, seletor PF/PJ e prévia de nome de exibição. | Dados básicos primeiro, cabeçalho acessível e coleções abertas em sheets. |

O roteiro permanece como referência para a futura feature de UI. A paridade funcional foi aceita; as melhorias de legibilidade, espaçamento, contraste e alinhamento serão especificadas e validadas no novo SDD visual.
