# Wireframe — Rinos Drive em desktop

```text
┌────────────────────────────── Rinos Drive ──────────────────────────────┐
│ PAINEL A                              ║ PAINEL B                       │
│ ┌────────────┬──────────────────────┐ ║ ┌────────────┬────────────────┐ │
│ │ ÁRVORE     │ Meu Drive / Projetos  │ ║ │ ÁRVORE     │ Empresa / Docs │ │
│ │ Meu Drive  │ [Árvore][Painel] ⋯    │ ║ │ Meu Drive  │ [Árvore] ⋯     │ │
│ │  Projetos  │ [Grade|Lista|Detalhes]│ ║ │ Empresa    │ [G|L|D]        │ │
│ │  Lixeira   │                      │ ║ │  Docs      │                │ │
│ │ Empresa    │ Coleção com scroll   │ ║ │  Lixeira   │ Coleção        │ │
│ │  Lixeira   │ interno              │ ║ │            │                │ │
│ │ Compartilh.│                      │ ║ │            │                │ │
│ │ Exportações│                      │ ║ │            │                │ │
│ ├────────────┼──────────────────────┤ ║ ├────────────┼────────────────┤ │
│ │ Uso        │ Status compacto      │ ║ │ Uso        │ Status         │ │
│ └────────────┴──────────────────────┘ ║ └────────────┴────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

- Instância única, aberta pela ferramenta global da topbar; um painel inicialmente.
- As duas cópias do componente são iguais, exceto Compartilhados comigo, Exportações e o comando de segundo painel, exclusivos do primário. Exportações só aparece após uma solicitação na janela.
- Localização, seleção, árvore, toolbar, rolagem, detalhes e visualização são independentes. Detalhes é o padrão; preferências são locais por usuário e painel.
- O divisor central redistribui a largura. Cada árvore também tem divisor, limitado à largura do seu painel.
- Ícone do drive abre/recolhe sua árvore; texto sempre navega para sua raiz. Cada drive real possui sua própria lixeira.
- Drop na árvore ou coleção confirma Copiar/Mover/Cancelar; mesmo drive sugere Mover, diferentes sugerem Copiar. Origem somente leitura não permite Mover.
- O conteúdo ocupa a altura restante; o status é uma faixa no rodapé. Excesso de comandos vai para o popover sem perder ações.
- Até 700px, o segundo painel é desativado; não se converte em modal ou drawer.
