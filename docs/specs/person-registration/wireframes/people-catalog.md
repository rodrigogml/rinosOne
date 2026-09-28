# Wireframe — Catálogo de Pessoas

Baixa fidelidade para `INT-WEB-PEOPLE-001` e `INT-WEB-PEOPLE-005`. O texto da [especificação de interface](../interface-spec.md) é a fonte de verdade.

## Desktop e tablet largo

```text
+--------------------------------------------------------------------------------+
| Pessoas da organização                                      [ + Nova Pessoa ]  |
| Buscar nome, documento ou contato [______________________] [Filtros] [Atualizar]|
+--------------------------------------------------------------------------------+
| Ativas | Inativas | Todas     Tipo [Todos v] Estado [Ativas v]                 |
+--------------------------------------------------------------------------------+
| Nome / documento                 | Tipo | Contatos       | Situação | Ações    |
|----------------------------------+------+----------------+----------+----------|
| Ana Souza / CPF final 123         | PF   | 2 contatos     | Ativa    | Abrir ⋮  |
| Empresa Horizonte / sem documento | PJ   | 1 contato      | Inativa | Abrir ⋮  |
+--------------------------------------------------------------------------------+
| 42 Pessoas encontradas                                 < 1 2 3 >             |
+--------------------------------------------------------------------------------+
```

- `Ações` abre menu contextual: abrir, editar, duplicar, inativar/reativar ou excluir, conforme permissão e estado.
- Filtros avançados ficam em painel lateral não modal quando houver espaço.
- A seleção de linha abre o detalhe/formulário apenas por ação explícita ou duplo clique em desktop; o menu mantém as mesmas alternativas.

## Telefone e tablet estreito

```text
+---------------------------------------+
| Pessoas                     [ + ]     |
| [ Buscar Pessoas...              ]    |
| [Ativas v] [Filtros] [Atualizar]      |
+---------------------------------------+
| Ana Souza                           > |
| PF · CPF final 123 · 2 contatos       |
| Ativa                                 |
|---------------------------------------|
| Empresa Horizonte                    > |
| PJ · Sem documento · 1 contato         |
| Inativa                               |
+---------------------------------------+
|        <  1 de 3  >                    |
+---------------------------------------+
```

- Filtros abrem sheet modal; uma Pessoa abre por toque único no card.
- O botão `+` possui nome acessível “Nova Pessoa”.
- Ações destrutivas não ficam no gesto de deslizar; ficam na tela de detalhe ou no menu explicitamente acionado.
