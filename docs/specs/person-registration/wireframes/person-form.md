# Wireframe — Formulário de Pessoa

Baixa fidelidade para `INT-WEB-PEOPLE-002`, `INT-WEB-PEOPLE-003`, `INT-WEB-PEOPLE-004` e `INT-WEB-PEOPLE-006`. O texto da [especificação de interface](../interface-spec.md) é a fonte de verdade.

## Desktop e tablet largo

```text
+--------------------------------------------------------------------------------+
| < Pessoas / Nova Pessoa                                  [Cancelar] [Salvar] |
+--------------------------------------------------------------------------------+
| [Dados básicos] [Endereços] [Contatos] [Contas] [Pix] [Relacionamentos]       |
+--------------------------------------------------------------------------------+
| Tipo  ( PF / PJ )                  Situação: Ativa                            |
| Nome/Razão social [_______________________________________________]           |
| Apelido/Nome fantasia [____________________________________________]          |
| Nome de exibição: Nome (apelido)                                               |
| CPF/CNPJ [__________________]       RG / documento complementar [________]   |
| Nascimento/Fundação [__________]    Passaporte [_______________________]     |
| Observações [___________________________________________________________]      |
|             [___________________________________________________________]      |
+--------------------------------------------------------------------------------+
| Endereços                                             [ + Adicionar endereço ] |
| [Residencial · Brasil · Município] Rua, número, complemento             [⋮]  |
+--------------------------------------------------------------------------------+
```

- Seções de coleções preservam o mesmo local de ação: cabeçalho, lista de itens e adicionar.
- A edição de um item ocorre em painel lateral; exclusão de item exige confirmação somente quando houver conteúdo já salvo.

## Telefone e tablet estreito

```text
+---------------------------------------+
| < Pessoas              [Salvar]       |
| Nova Pessoa                           |
| [ Dados ] [ Mais v ]                  |
+---------------------------------------+
| Tipo       [ PF v ]                    |
| Nome       [____________________]      |
| Apelido    [____________________]      |
| CPF        [____________________]      |
| Nome de exibição                       |
| Nome (apelido)                         |
|                                       |
| [ Endereços (1)                     > ]|
| [ Contatos (2)                      > ]|
| [ Contas bancárias (0)              > ]|
| [ Chaves Pix (0)                    > ]|
| [ Relacionamentos (1)               > ]|
+---------------------------------------+
```

- Cada coleção abre sheet de tela cheia e retorna ao formulário sem descartar alterações não salvas.
- O rodapé de ações fica acima de teclado virtual e área segura; `Salvar` permanece disponível apenas quando o formulário é válido e alterado.
- Diálogos de duplicação, inativação, reativação e exclusão usam camada modal sobre esta tela, com foco inicial no título ou descrição e retorno de foco ao acionador.
