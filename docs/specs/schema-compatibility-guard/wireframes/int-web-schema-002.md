# INT-WEB-SCHEMA-002 — Wireframe de baixa fidelidade

A estrutura ilustra o tratamento contextual; o texto de [interface-spec.md](../interface-spec.md) é a fonte de verdade.

## Desktop — seletor de organização

```text
┌──────────────────── Organizações ────────────────────┐
│  Organização Alfa                         [ ativa ]  │
│  Organização Beta       Temporariamente indisponível │
│  para atualização                                 │
│                                                     │
│  [ Escolher outra organização ]       [ Fechar ]    │
└─────────────────────────────────────────────────────┘
```

## Telefone — folha modal

```text
┌─────────────────────────────────────────────────────┐
│  Organizações                                  [ × ] │
│─────────────────────────────────────────────────────│
│  Organização Beta                                  │
│  Temporariamente indisponível para atualização.     │
│                                                     │
│  [ Escolher outra organização ]                     │
│  [ Entendi ]                                        │
└─────────────────────────────────────────────────────┘
```

- O estado é textual; ícone e cor, quando presentes, são complementares.
- Se o contexto ativo se tornar incompatível, a notificação recebe foco e a área de trabalho volta ao estado pessoal seguro.
