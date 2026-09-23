# API Checklist: Acesso de Usuário

**Purpose**: validar a qualidade dos requisitos e contratos da API de acesso, sem testar implementação.  
**Created**: 2026-09-22  
**Feature**: [spec.md](../spec.md)

## Contrato e Segurança de Respostas

- [x] CHK001 - As operações necessárias para cadastro, confirmação, senha, acesso sem senha e controle de sessões possuem método, autenticação, request e resposta documentados? [Completude, Contract §Iniciar cadastro–§Encerrar sessões] {auto}
- [x] CHK002 - O versionamento da fronteira está declarado e consistente com a arquitetura da feature? [Consistência, Plan §Resumo; Contract §API de Acesso] {auto}
- [x] CHK003 - Os payloads e campos de borda usam uma convenção única e mapeada para backend, interface e banco? [Consistência, Plan §Convenções de Borda; Contract §API de Acesso] {auto}
- [x] CHK004 - As respostas de cadastro e acesso sem senha são definidas como neutras quanto à existência de conta? [Segurança, Contract §API de Acesso; Spec FR-014–015] {auto}
- [x] CHK005 - Os cenários de credencial inválida, e-mail não validado, entrada inválida e limite excedido possuem status e código de erro definidos? [Completude, Contract §Erros] {auto}

## Sessão, Limites e Confiabilidade

- [x] CHK006 - A exigência de autenticação é declarada para definir senha e controlar sessões, enquanto cadastro e confirmação permanecem públicos? [Cobertura, Contract §Definir senha–§Encerrar sessões] {auto}
- [x] CHK007 - A emissão de link e código é especificada como uma única emissão cujo consumo invalida ambos os meios? [Clareza, Spec FR-010–013; Contract §Concluir acesso sem senha] {auto}
- [x] CHK008 - A expiração de 10 minutos e os limites configuráveis de tentativas e emissões estão definidos em requisito e plano? [Clareza, Spec FR-014, FR-022; Research §Decision 4] {auto}
- [x] CHK009 - A política de repetição de requisições que criam emissão produz efeito definido — substituir a emissão anterior — em vez de criar múltiplas credenciais válidas? [Consistência, Spec FR-013; Data Model §AuthenticationChallenge] {auto}
- [x] CHK010 - O contrato prevê validação de schema de request e response nos dois lados da fronteira? [Rastreabilidade, Plan §Convenções de Borda; Quickstart §Cenário 5] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há itens `{humano}` ou gaps de contrato nesta rodada.
