# Wireframe: Área de Trabalho Desktop

Relaciona INT-WEB-WORKSPACE-001 e INT-WEB-WORKSPACE-002.

```text
┌────────────────────────────────────────── Top bar ─────────────────────────────────────────┐
│ [marca]                                                        [organização] [pessoa]        │
├───────────────┬─────────────────────────────────────────────────────────────────────────────┤
│ [‹]           │ ┌──── Mega menu da categoria ─────────────────────────────────────────────┐ │
│ ◈ Categoria A │ │ Grupo A                Grupo B                 Grupo C                    │ │
│ ◇ Categoria B │ │ [ícone] Destino        [ícone] Destino         [ícone] Destino            │ │
│ ◇ Categoria C │ └─────────────────────────────────────────────────────────────────────────┘ │
│               │                                                                             │
│ rail          │                         Palco da Área de trabalho                           │
│ expandido     │                superfície ativa ou estado neutro, sem dados fictícios        │
│ ou recolhido  │                                                                             │
│               │                                                                             │
│               ├────────────────────────── Barra de tarefas ─────────────────────────────────┤
│               │ [ícone Superfície A ●]  [ícone Superfície B]                                │
└───────────────┴─────────────────────────────────────────────────────────────────────────────┘
```

- O mega menu fica abaixo da top bar, sobre o início do palco, e fecha por Escape, destino, categoria repetida ou clique externo.
- O rail recolhido preserva ícones, tooltip e nome acessível; a seleção de destino recolhe o rail somente em desktop.
