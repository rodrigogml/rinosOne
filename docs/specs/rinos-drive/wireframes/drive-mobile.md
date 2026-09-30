# Wireframe — Rinos Drive unificado em telefone

```text
┌────────────────────────────── Rinos Drive ──────────────────────────────┐
│ [Árvore] Meu Drive / Projetos                   [Atualizar] [Painéis]    │
│ [Grade] [Lista] [Detalhes] [Tabela]                                  ⋮  │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ ícone  Plano.pdf                                           2,0 MB    │ │
│ │ pasta  Contratos                                              ›      │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│ [Transferência: copiando 3 de 12]                                       │
└─────────────────────────────────────────────────────────────────────────┘

Árvore (drawer modal):
┌─────────────────────────────────────────────────────────────────────────┐
│ Drives                                                               [×] │
│ ▾ Meu Drive                                                             │
│   ▸ Projetos                                                             │
│   Lixeira                                                                │
│ ▾ Organização Alfa                                                      │
│   ▸ Financeiro                                                           │
│   Lixeira                                                                │
│ ◇ Compartilhados comigo                                                  │
└─────────────────────────────────────────────────────────────────────────┘

Painel paralelo (modal quase integral):
┌─────────────────────────────────────────────────────────────────────────┐
│ Organização Alfa / Financeiro                                        [×] │
│ [Grade] [Lista] [Detalhes]                                              │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ pasta  Destino                                                     › │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│             [Copiar itens selecionados para este local]                  │
└─────────────────────────────────────────────────────────────────────────┘
```

## Regiões e regras

- Há uma coleção principal por vez; árvore, detalhes, painel paralelo e operações usam modal/drawer com foco contido.
- A ação Painéis abre o segundo local sem comprimir a coleção ou gerar scroll horizontal no canva.
- Drag possui alternativa explícita por botão para escolher o destino e abrir o mesmo diálogo Copiar/Mover/Cancelar.
- A faixa de transferência fica dentro do Drive, acima da coleção, e pode ser reaberta pelo cabeçalho; não usa taskbar móvel nem ocupa safe area permanentemente.
- Todas as ações respeitam safe area, teclado virtual, alvo mínimo de toque e rolagem interna da coleção/modal.
