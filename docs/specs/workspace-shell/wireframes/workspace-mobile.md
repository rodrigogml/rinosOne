# Wireframe: Área de Trabalho em Telefone

Relaciona INT-WEB-WORKSPACE-004.

```text
┌───────────────────────────────┐
│ [marca / abrir navegação] [◉] │
├───────────────────────────────┤
│ [Superfícies abertas ▾]       │
│                               │
│       Palco: uma superfície   │
│       ativa por vez           │
│                               │
└───────────────────────────────┘

Painel de navegação             Painel de superfícies
┌───────────────────────┐       ┌───────────────────────┐
│ [marca]           [×] │       │ Superfícies abertas [×]│
│ Categoria A           │       │ ● Superfície ativa     │
│  › Grupo / destino    │       │   Outra superfície [×] │
│ Categoria B           │       │   Outra superfície [×] │
└───────────────────────┘       └───────────────────────┘
```

- Cada painel é modal e mutuamente exclusivo; diálogo de confirmação fica acima de ambos.
- A marca continua sem moldura visual de botão, mas tem nome acessível de abertura da navegação.
