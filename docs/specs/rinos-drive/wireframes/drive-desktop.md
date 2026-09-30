# Wireframe — Rinos Drive unificado em desktop

```text
┌──────────────────────────────────────── Rinos Drive ──────────────────────────────────────────────┐
│ [ícone Drive] Rinos Drive                         [Atualizar] [Abrir painel paralelo]              │
│ ┌───────────────────────┬──────────────────────────────────────┬──────────────────────────────────┐ │
│ │ CATÁLOGO E ÁRVORE     │ PAINEL A                             │ PAINEL B                         │ │
│ │ ▾ Meu Drive           │ Meu Drive / Projetos                 │ Organização Alfa / Financeiro    │ │
│ │   ▸ Projetos          │ [Grade][Lista][Detalhes][Tabela]     │ [Grade][Lista][Detalhes][Tabela]│ │
│ │   Lixeira             │ ──────────────────────────────────── │ ──────────────────────────────── │ │
│ │ ▾ Organização Alfa    │ ┌─────────┐ ┌─────────┐              │ ┌─────────┐ ┌─────────┐            │ │
│ │   ▸ Financeiro        │ │ Pasta A │ │ Plano   │  ── arrastar ▶│ │ Pasta B │ │ Proposta│            │ │
│ │   Lixeira             │ └─────────┘ └─────────┘              │ └─────────┘ └─────────┘            │ │
│ │ ▸ Organização Beta    │                                      │                                  │ │
│ │ ◇ Compartilhados      │ [progresso seguro da transferência]  │ [uso do drive de origem]          │ │
│ │   comigo              │                                      │                                  │ │
│ └───────────────────────┴──────────────────────────────────────┴──────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## Regiões e regras

- A janela é instância única e abre pelo botão global Drive na topbar, não por menu pessoal ou tenant.
- A coluna esquerda contém catálogo e árvore lazy. Cada drive real possui lixeira própria; Compartilhados comigo é raiz virtual sem lixeira.
- Um painel é o padrão. O botão de painel paralelo cria a segunda coluna; ambas possuem localização, seleção, toolbar, rolagem e estados próprios.
- Drop de um painel no outro nunca executa diretamente: abre diálogo local com Copiar, Mover e Cancelar. Mesmo drive sugere Mover; drives diferentes sugerem Copiar.
- Detalhes continuam drawer/painel interno e não alteram taskbar ou canva da aplicação.
- Quando a largura não comportar dois painéis com conteúdo útil, o segundo painel muda para drawer/modal, conforme regra responsiva.
