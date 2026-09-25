# Wireframe: Taskbar e Confirmação de Descarte

Relaciona INT-WEB-WORKSPACE-003.

```text
┌──────────────────────────────────── Palco ────────────────────────────────────┐
│ [ícone] Título da superfície                                                [X] │
│                                                                                │
│ Conteúdo da superfície ativa                                                  │
├────────────────────────────────────────────────────────────────────────────────┤
│ [● Ícone A] [  Ícone B] [  Ícone C]                                             │
└────────────────────────────────────────────────────────────────────────────────┘

                         ┌──── Alterações não salvas ────┐
                         │ Há trabalho não confirmado.   │
                         │                               │
                         │ [Cancelar] [Descartar]         │
                         └────────────────────────────────┘

                    [✓ Feedback curto e enfileirado, sem roubar foco]
```

- A taskbar mostra somente ícones centralizados; o ativo é maior e recebe um pill inferior. Nome acessível e tooltip permanecem disponíveis sem texto visível.
- A taskbar não possui fundo nem linha própria: seus ícones flutuam sobre o fundo da área à direita do menu.
- O diálogo da área de trabalho cobre menu, palco e taskbar, mas não a topbar; cancelar devolve foco ao originador. Cada janela possui uma pilha local de diálogos, limitada à própria janela; o topo bloqueia somente seu conteúdo, pode abrir outro diálogo acima, permanece ao alternar entre janelas e é descartado ao fechar sua própria janela.
