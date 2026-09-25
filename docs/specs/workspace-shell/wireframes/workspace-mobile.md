# Wireframe: Área de Trabalho em Telefone

Relaciona INT-WEB-WORKSPACE-004.

```text
┌───────────────────────────────┐
│ [marca / abrir navegação] [▣] [◉] │
├───────────────────────────────┤
│       Palco: uma superfície   │
│       ativa por vez           │
│                               │
└───────────────────────────────┘

Painel de navegação                 Diálogo de superfícies (até 95% do viewport)
┌───────────────────────┐           ┌────────────────────────────────────┐
│ [marca]           [×] │           │ Superfícies abertas             [×] │
│ Categoria A           │           │ ● Superfície ativa                  │
│  › Grupo / destino    │           │   Outra superfície              [×] │
│ Categoria B           │           │   Outra superfície              [×] │
└───────────────────────┘           └────────────────────────────────────┘
```

- O ícone `▣` representa a alternância de janelas e aparece somente quando há superfícies abertas; fica imediatamente antes do seletor de organização `◉`.
- Navegação e diálogo de superfícies são modais e mutuamente exclusivos; o diálogo de confirmação fica acima de ambos.
- O seletor de superfícies não participa do fluxo vertical do palco e sua lista rola internamente quando necessário.
- A marca continua sem moldura visual de botão, mas tem nome acessível de abertura da navegação.
