# Wireframe — INT-WEB-ADMIN-001

Baixa fidelidade. A estrutura, os estados e as regras textuais em `../interface-spec.md` são a fonte de verdade.

## Desktop

```text
+--------------------------------------------------------------------------------+
| Usuários e acessos                                                            |
| [Organização] Empresa Exemplo                         [Atualizar] [Fechar]   |
+--------------------------------------------------------------------------------+
| Pessoas e acessos | Papéis e grupos | Auditoria | Avançado                    |
+-------------------------------+------------------------------------------------+
| Buscar pessoa ou identidade... | Ana Silva                                      |
| [___________________________] | Acesso no contexto: Empresa Exemplo           |
|                                |                                                |
| Ana Silva                      | Fontes de acesso                              |
| Administrador                  | - Papel: Administrador do tenant              |
| 3 fontes de acesso             | - Grupo: Financeiro                           |
|                                | - Acesso temporário até 30/09                 |
| Bruno Santos                   |                                                |
| Leitura                         | [Ver o que pode fazer] [Alterar papéis]       |
|                                |                                                |
| [Adicionar pessoa]             | Ações avançadas somente se autorizadas         |
+-------------------------------+------------------------------------------------+
```

## Telefone

```text
+--------------------------------------+
| ← Usuários e acessos                 |
| Organização · Empresa Exemplo         |
| [Pessoas e acessos v]                 |
+--------------------------------------+
| Buscar pessoa...                      |
| [_______________________________]    |
|                                      |
| Ana Silva                       >     |
| Administrador · 3 fontes               |
|                                      |
| Bruno Santos                    >     |
| Leitura                              |
+--------------------------------------+

Selecionar pessoa abre painel/tela:
+--------------------------------------+
| ← Ana Silva                           |
| Acesso em Empresa Exemplo             |
|                                      |
| Fontes de acesso                       |
| Papel · Administrador                  |
| Grupo · Financeiro                     |
|                                      |
| [Ver o que pode fazer]                |
| [Alterar papéis]                       |
+--------------------------------------+
```

O selo do contexto permanece acima da navegação em todos os breakpoints. Confirmações de alteração são diálogos acessíveis com alvo, contexto, efeito e vigência.
