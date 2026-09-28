# Wireframe — Rinos Drive em telefone

```text
┌─────────────────────────────── Rinos Drive ───────────────────────────────┐
│ [Árvore]  Rinos Drive Pessoal                            [Upload] [Mais]  │
│ Meus arquivos / Documentos / 2026                                        │
│ [Buscar arquivos nesta pasta]                                             │
│ [Grade] [Lista] [Detalhes] [Tabela]                                      │
│ ┌───────────────────────────────────────────────────────────────────────┐ │
│ │ ícone  Contrato.pdf                                      2,0 MB   ⋮   │ │
│ │ ícone  Proposta.docx                                     850 KB   ⋮   │ │
│ │ pasta  Fiscal                                                    ›     │ │
│ └───────────────────────────────────────────────────────────────────────┘ │
│ [2 selecionados]                                      [Baixar] [Lixeira] │
└───────────────────────────────────────────────────────────────────────────┘

Árvore / detalhes (drawer modal):
┌───────────────────────────────────────────────────────────────────────────┐
│ Árvore                                                               [×]  │
│ ▾ Meus arquivos                                                           │
│   ▸ Projetos                                                               │
│   ▾ Documentos                                                             │
│     ▸ 2026                                                                 │
│   Lixeira                                                                  │
└───────────────────────────────────────────────────────────────────────────┘
```

- A coleção permanece o conteúdo principal; árvore e detalhes abrem em drawer modal com foco contido.
- Ações de seleção usam uma faixa contextual compacta e nunca ficam escondidas pelo teclado virtual ou safe area.
- O menu estrutural e o seletor de janelas continuam na topbar da aplicação; esta tela não cria nova taskbar móvel.
