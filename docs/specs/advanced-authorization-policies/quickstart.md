# Cenários de Validação

1. Grant com limite de valor e contexto confiável acima do limite → **esperado:** negação, mesmo com grant válido.
2. Delegar acesso não delegável ou criar ciclo → **esperado:** recusa auditada.
3. Solicitar elevação, aprovar por pessoa independente e expirar → **esperado:** allow somente no intervalo aprovado.
4. Sincronização SCIM inválida tenta remover último administrador → **esperado:** rejeição, auditoria e acesso preservado.
5. Roundtrip web: solicitar acesso temporário → API real → **esperado:** estado pendente/acessível sem expor aprovadores indevidos.
