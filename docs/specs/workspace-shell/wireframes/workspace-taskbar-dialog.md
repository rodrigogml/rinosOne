# Wireframe: Taskbar e Confirmação de Descarte

Relaciona INT-WEB-WORKSPACE-003.

```text
┌──────────────────────────────────── Palco ────────────────────────────────────┐
│ [ícone] Título da superfície                                           [fechar] │
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

- Apenas um item da taskbar é ativo; o estado também é anunciado semanticamente.
- O diálogo bloqueante fica acima do palco e da taskbar; cancelar devolve foco ao originador.
