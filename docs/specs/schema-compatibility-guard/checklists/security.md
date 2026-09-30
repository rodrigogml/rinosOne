# Checklist de Segurança: Compatibilidade de Schemas e Guarda Operacional

**Finalidade**: validar os requisitos de falha fechada, exposição mínima e separação de privilégios da feature.
**Criado em**: 2026-09-30
**Feature**: [spec.md](../spec.md)

## Falha fechada e acesso

- [x] CHK001 - A incompatibilidade global ou erro de leitura é definida como bloqueio antes de qualquer função de negócio, em vez de autorização por padrão? [Segurança, Spec §FR-SCG-001 a 004; Plan §Guarda global] {auto}
- [x] CHK002 - A incompatibilidade de organização é validada antes de contexto, conexão de runtime e operação contextual, não apenas pela visibilidade de menu? [Segurança, Spec §FR-SCG-005, 006 e 009; Plan §Guarda por organização] {auto}
- [x] CHK003 - A atualização da mesma organização possui requisito explícito de serialização e de recusa de execução simultânea? [Concorrência, Spec §FR-SCG-009 e FR-SCG-INFRA-LOCK; Data model §Transições] {auto}
- [x] CHK004 - Uma organização incompatível não pode comprometer espaço pessoal, outra organização compatível ou dados de um contexto distinto? [Isolamento, Spec §FR-SCG-006; Spec §Casos de borda; Interface §Regras entre superfícies] {auto}

## Exposição e privilégios

- [x] CHK005 - Mensagens e contratos públicos excluem schema, migration, versões, SQL, credenciais e códigos internos de falha? [Exposição mínima, Spec §FR-SCG-003 e 007; Contract §§Bloqueio global e de organização] {auto}
- [x] CHK006 - A interface não oferece comando de migration, deploy, diagnóstico técnico ou recuperação manual a usuários finais? [Privilégio mínimo, Spec §Escopo; Interface §INT-WEB-SCHEMA-001 e 002] {auto}
- [x] CHK007 - O plano mantém a execução de DDL fora de credenciais de runtime e fora de requisições web? [Separação de privilégios, Plan §Contexto técnico e §Atualização de organizações existentes; Research §Decisões 1 e 4] {auto}
- [x] CHK008 - Dados contextuais antigos são removidos ou tornam-se não acionáveis quando a organização é bloqueada? [Integridade de sessão/contexto, Spec §FR-SCG-006 e 009; Interface §INT-WEB-SCHEMA-002] {auto}

## Observabilidade segura

- [x] CHK009 - A evidência operacional permite recuperação sem registrar mensagens de banco, segredos ou conteúdo de negócio? [Logging seguro, Spec §FR-SCG-013; Data model §tenantSchemaUpdate; Plan §Modelo de dados] {auto}
- [x] CHK010 - A telemetria de interface exclui identidade, URL completa, nomes de organização, schema, migrations e dados de formulário ou arquivo? [Privacidade, Interface §INT-WEB-SCHEMA-001, 002, PEOPLE-001 e DRIVE-001] {auto}

## Notas

A feature exige testes de autorização negativa e de respostas seguras; estes são validação futura de implementação, não itens deste quality gate.
