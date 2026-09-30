# Cenários de Validação: Compatibilidade de Schemas e Guarda Operacional

## Cenário 1: Bloqueio global antes de uma operação

1. Preparar uma cópia descartável do ambiente em que o catálogo global possua uma migration pendente.
2. Abrir uma página funcional da plataforma.
3. Solicitar uma operação funcional da API.
4. **Esperado**: ambos retornam indisponibilidade controlada; nenhuma operação de domínio é executada e não há detalhes técnicos na página ou no payload.

## Cenário 2: Recuperação global sem reinício de usuário

1. Com o bloqueio global ativo, aplicar o catálogo global pela etapa operacional autorizada.
2. Repetir a abertura da página e a solicitação funcional.
3. **Esperado**: novas solicitações passam a ser atendidas normalmente, sem limpeza de navegador, novo login ou reinicialização de instância exigida da pessoa usuária.

## Cenário 3: Isolamento por organização

1. Preparar duas organizações ativas para a mesma pessoa.
2. Deixar apenas uma com catálogo organizacional pendente e iniciar seu ciclo de atualização.
3. Tentar selecionar e operar a organização em atualização.
4. Selecionar e operar a organização compatível.
5. **Esperado**: a primeira retorna `TENANT_SCHEMA_UNAVAILABLE`; a segunda e os recursos pessoais continuam funcionais.

## Cenário 4: Falha e retomada da atualização de organização

1. Iniciar atualização de uma organização e simular uma falha transitória.
2. Verificar que ela permanece indisponível e que somente uma nova tentativa é agendada.
3. Concluir a tentativa seguinte com o catálogo completo.
4. **Esperado**: a organização só volta a aceitar contexto após a confirmação; nenhum ciclo concorrente é criado.

## Cenário 5: Roundtrip API e interface

1. Colocar uma organização em atualização por meio do ciclo operacional real.
2. Solicitar o contexto dessa organização pelo contrato real e capturar o payload.
3. Confirmar o código, o status e os campos contra [schema-compatibility.md](contracts/schema-compatibility.md).
4. Abrir a interface com a mesma organização selecionada ou solicitar sua seleção.
5. **Esperado**: o cliente interpreta o código estável, remove o uso contextual incompatível e apresenta a mensagem localizada sem detalhes técnicos.
