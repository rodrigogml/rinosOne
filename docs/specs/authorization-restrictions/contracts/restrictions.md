# Contrato Interno: Restrictions

## Escrita administrativa

`createRestriction`, `updateRestriction`, `deactivateRestriction` e `removeRestriction` recebem permission, subject, scope, tenant quando TENANT e vigência em camelCase. O serviço retorna estado atual ou erro seguro `AUTH_RESTRICTION_*`.

## Decisão

O contrato `check` retorna `allowed: false` e `reasonCode: RESTRICTION_APPLIES` quando houver restriction ativa e vigente direta ou por grupo. O motivo detalhado é exclusivo de `explain` administrativo futuro.
