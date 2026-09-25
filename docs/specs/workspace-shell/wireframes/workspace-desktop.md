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
│ menu integral │                         Área de janelas                                     │
│ expandido     │                janela ativa ou estado neutro, sem moldura ou texto             │
│ ou recolhido  │                                                                             │
│               │                                                                             │
│               ├────────────────────────── Barra de tarefas ─────────────────────────────────┤
│               │                    [ícone A] ━  [ícone B]                                  │
└───────────────┴─────────────────────────────────────────────────────────────────────────────┘
```

- O mega menu fica abaixo da top bar, sobre toda a área de janelas à direita do menu, e fecha por Escape, destino ou clique externo. Ele não desloca a área de janelas.
- O rail recolhido preserva ícones, tooltip e nome acessível; a seleção de destino recolhe o rail somente em desktop.
- A taskbar centraliza somente ícones. O ícone ativo é maior e recebe pill inferior; hover amplia o ícone. O X de fechamento está no canto direito do cabeçalho da janela.
