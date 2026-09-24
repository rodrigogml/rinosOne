# Wireframes de Baixa Fidelidade: Contexto de Tenant

## Desktop — barra e seletor

```text
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│ [ marca ]                                             [ TA ] [ RU ]                       │
│                                                      tenant  usuário                      │
└─────────────────────────────────────────────────────────────────────────────────────────┘
                                                             │
                                                             ▼
                                               ┌───────────────────────────────┐
                                               │ Organização atual              │
                                               │ [ ? ] Nenhuma selecionada      │
                                               │                               │
                                               │ Organizações disponíveis        │
                                               │ [ AI ] Acme Industrial    >    │
                                               │ [ RS ] Rinos Serviços     >    │
                                               │                               │
                                               │ + Criar organização             │
                                               │ Gerenciar organizações          │
                                               └───────────────────────────────┘
```

`TA` é o avatar de tenant; `RU` é o avatar pessoal. Sem tenant selecionado, `TA` exibe `?` e abre o seletor.

## Desktop — criação e acompanhamento

```text
                    ┌─────────────────────────────────────┐
                    │ Organizações                         │
                    │                                     │
                    │ Criar organização                    │
                    │ Nome [__________________________]    │
                    │                     [ Criar ]        │
                    │ ─────────────────────────────────── │
                    │ Acme Industrial                       │
                    │ Preparando…  dados ainda indisponíveis│
                    │                                     │
                    │ Rinos Serviços        [ Desabilitar ] │
                    └─────────────────────────────────────┘
```

## Telefone — barra e folha de seleção

```text
┌────────────────────────────────────┐
│ [ marca ]                 [ ? ][RU] │
└────────────────────────────────────┘
                    │
                    ▼
┌────────────────────────────────────┐
│ Organização atual              [×]  │
│ Nenhuma selecionada                 │
│                                    │
│ Disponíveis                         │
│ [ AI ] Acme Industrial          >   │
│ [ RS ] Rinos Serviços           >   │
│                                    │
│ [ + Criar organização ]             │
│ [ Gerenciar organizações ]          │
└────────────────────────────────────┘
```

No telefone, o mesmo conteúdo do seletor usa folha modal ancorada à base da tela para preservar área tocável e leitura.
