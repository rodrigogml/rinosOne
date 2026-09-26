# Contrato: Políticas Avançadas

Operações versionadas em JSON gerenciam policies, delegations, access requests, approval decisions, SoD, service identities e directory mappings. Payloads usam camelCase, IDs persistidos são inteiros positivos e erros usam envelope seguro.

O adaptador `GroupDirectoryProvider` recebe grupos externos verificados e devolve alterações candidatas; o serviço aplica mapeamento, invariantes e auditoria. Falha/entrada inválida não concede acesso.
