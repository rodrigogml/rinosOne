# Wireframe — INT-WEB-SHARING-001

Baixa fidelidade. A estrutura, os estados e as regras textuais em `../interface-spec.md` são a fonte de verdade.

## Desktop — painel lateral do Drive

```text
Drive                                                +--------------------------+
                                                      | Compartilhar              |
Relatórios / Setembro                                | Organização · Empresa... |
                                                     +--------------------------+
                                                     | Relatório mensal.pdf      |
                                                     | Responsável pelo          |
                                                     | workspace: Empresa Ex...  |
                                                     +--------------------------+
                                                     | Pessoas com acesso        |
                                                     | Ana Silva                 |
                                                     | Leitura direta      [v]   |
                                                     |                          |
                                                     | Grupo Financeiro          |
                                                     | Herdado de /Relatórios    |
                                                     | [Ver origem]              |
                                                     +--------------------------+
                                                     | Adicionar acesso          |
                                                     | Pessoa [______________]  |
                                                     | Nível  [Leitura       v] |
                                                     | [Cancelar] [Compartilhar] |
                                                     +--------------------------+
```

## Telefone — página de painel

```text
+--------------------------------------+
| ← Compartilhar                       |
| Organização · Empresa Exemplo         |
| Relatório mensal.pdf                  |
| Responsável pelo workspace: Empresa   |
+--------------------------------------+
| Acesso direto                         |
| Ana Silva · Leitura              [v]  |
|                                      |
| Acesso herdado                         |
| Grupo Financeiro                       |
| Origem: /Relatórios              [>]  |
|                                      |
| + Adicionar acesso                     |
+--------------------------------------+
```

Relações herdadas são visualmente diferenciadas por texto e origem, não apenas por cor. O nome do responsável pelo workspace é informativo e não possui ação de transferência.
