# Wireframe — Rinos Drive em telefone

```text
┌────────────────── Rinos Drive ──────────────────┐
│ Meu Drive / Projetos                            │
│ [Árvore]            [Grade|Lista|Detalhes] [⋯]   │
│ ┌─────────────────────────────────────────────┐ │
│ │ ícone Plano.pdf                     2 MB     │ │
│ │ pasta Contratos                              │ │
│ │                                             │ │
│ │ Coleção com rolagem interna                  │ │
│ └─────────────────────────────────────────────┘ │
│ 2 Itens [1|1]                                   │
└─────────────────────────────────────────────────┘

Árvore (drawer):
┌─────────────────────────────────────────────────┐
│ Árvore                                      [×] │
│ Meu Drive                                       │
│   Projetos                                      │
│   Lixeira                                       │
│ Organização Alfa                                │
│   Financeiro                                    │
│   Lixeira                                       │
│ Compartilhados comigo                           │
│ Exportações (após solicitação)                   │
└─────────────────────────────────────────────────┘
```

- Até 700px existe apenas um painel; não há comando de segundo painel nem restauração dessa preferência.
- Árvore e detalhes usam regiões sobrepostas; operações usam diálogos próprios. A coleção permanece com rolagem interna, sem aumentar o canvas.
- Toolbar usa os mesmos controles compartilhados; comandos excedentes ficam no popover de três pontos.
- A transferência em processamento continua no servidor; fechar a interface não cancela o job.
- Ações respeitam safe area, teclado virtual e alvos de toque.
