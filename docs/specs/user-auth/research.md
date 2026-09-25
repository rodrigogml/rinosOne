# Pesquisa Técnica: Acesso de Usuário

## Decision 1: Arquitetura modular de aplicação web

**Decision**: usar um monólito modular com backend em PHP 8.4 e Laravel 12, interface responsiva em Vue 3 e TypeScript, API JSON versionada e MySQL com `utf8mb4`.

**Rationale**: atende às tecnologias obrigatórias e separa domínio, bordas HTTP, persistência e interface sem antecipar produtos ou módulos futuros.

**Alternatives considered**: aplicação acoplada exclusivamente à renderização web; múltiplos serviços independentes desde o início. Ambas foram rejeitadas por violarem a fronteira API ou introduzirem complexidade prematura.

## Decision 2: Sessão e autenticação

**Decision**: manter sessões no servidor e autenticar a web por cookie de sessão, com proteção CSRF. A opção “Manter-me conectado” cria uma credencial persistente, armazenada apenas como hash no servidor, que pode reconstruir uma sessão server-side perdida. Não introduzir token de acesso público nesta fase.

**Rationale**: a única superfície humana aprovada é a web; sessões server-side permitem encerrar a sessão atual e invalidar as demais, enquanto a credencial persistente atende à continuidade de autenticação após fechar o navegador ou perder a sessão no servidor.

**Alternatives considered**: JWT como sessão principal; cookie persistente que contém identidade diretamente; login exclusivamente por senha. Foram rejeitados por ampliar a superfície de risco ou não atender aos requisitos aprovados.

## Decision 3: Emissões temporárias de e-mail

**Decision**: cada emissão de validação ou acesso sem senha terá um identificador, segredo armazenado somente como hash, propósito, expiração de 10 minutos, momento de consumo e marca de substituição. Um link e um código serão derivados da mesma emissão; o consumo de qualquer um invalida a emissão inteira.

**Rationale**: preserva a conveniência de duas alternativas sem permitir reutilização, concorrência ou permanência de segredos recuperáveis.

**Alternatives considered**: persistir links ou códigos em texto; manter emissões antigas válidas; criar emissões independentes para link e código. Foram rejeitadas por ampliar a superfície de risco e criar estados conflitantes.

## Decision 4: Limites configuráveis de acesso

**Decision**: adotar valores iniciais seguros e configuráveis por ambiente: no máximo 3 emissões por e-mail a cada 15 minutos, 10 emissões por origem a cada hora, 5 tentativas por emissão de código e 5 tentativas de senha por e-mail e origem a cada 15 minutos. Após exceder o limite, aplicar bloqueio temporário de 15 minutos.

**Rationale**: os valores reduzem abuso e adivinhação sem fixar uma política rígida no código; a implantação pode calibrá-los conforme o provedor de e-mail e o perfil de uso.

**Alternatives considered**: valores fixos no código; ausência de limite até uma fase futura. Ambos contrariam os requisitos de segurança aprovados.

## Decision 5: Configuração, e-mail e observabilidade

**Decision**: manter um arquivo `.env.example` comentado e versionado, ignorar `.env`, e usar a configuração do ambiente para o provedor de e-mail, credenciais e limites de autenticação. Eventos de segurança serão registrados de forma estruturada sem senha, código, link, token ou dados pessoais desnecessários.

**Rationale**: a infraestrutura de e-mail já existe, mas seus parâmetros reais pertencem exclusivamente ao ambiente de implantação.

**Alternatives considered**: credenciais no repositório; uma tabela de auditoria dedicada nesta primeira fase. A primeira é insegura e a segunda não é necessária para o escopo aprovado.

## Decision 6: Bootstrap, implantação e decisões operacionais

**Decision**: o bootstrap criará a aplicação web no repositório atual e adotará execução Linux com servidor web, PHP-FPM, MySQL, um worker de fila persistente para o envio de e-mail e um único scheduler para descarte de emissões vencidas. A fila usará o banco inicialmente; nenhum refresh externo, lock entre réplicas, rotação de chave própria ou rotina de backup específica é exigido por esta feature.

**Rationale**: o envio de e-mail não deve alongar a resposta de cadastro ou acesso. O scheduler limita-se ao descarte de dados temporários vencidos, necessário para atender à exclusão sem retenção; os demais mecanismos não são necessários sem produtos, integrações ou tarefas periódicas aprovadas.

**Alternatives considered**: envio síncrono de e-mail; infraestrutura de mensageria externa; scheduler para responsabilidades além da limpeza e múltiplos serviços. Foram rejeitados por risco de experiência ruim ou complexidade antecipada.

## Decision 7: Duração e recuperação de autenticação

**Decision**: sessões server-side não possuem expiração por inatividade. Sem “Manter-me conectado”, o cookie de sessão encerra ao fechar o navegador ou quando a sessão server-side se perder. Com a opção selecionada, uma credencial persistente recria a sessão automaticamente até logout ou revogação; a duração e demais parâmetros permanecem configuráveis por ambiente, com padrão sem expiração.

**Rationale**: a plataforma deve manter o usuário autenticado até logout quando ele solicitar persistência, mas não pode depender da sobrevivência de uma sessão específica no servidor.

**Alternatives considered**: prazo fixo obrigatório para toda sessão; persistência automática para todos os logins; não recuperar sessão perdida. Foram rejeitados por contrariar a escolha explícita do usuário ou por reduzir o controle sobre a permanência da autenticação.

## Decision 8: Retenção de emissões e logs de segurança

**Decision**: excluir emissões de e-mail usadas, vencidas ou substituídas e sessões invalidadas sem retenção. Reter logs de segurança por 30 dias por padrão, com período configurável por ambiente.

**Rationale**: emissões temporárias e sessões invalidadas não possuem valor operacional após o término do seu ciclo; 30 dias de logs permite diagnóstico recente sem manter informação além do necessário.

**Alternatives considered**: retenção permanente; ausência completa de logs; período fixo não configurável. Foram rejeitados por aumentar dados desnecessários, reduzir rastreabilidade ou impedir adequação por instância.

## Decision 9: Topologia de schemas para evolução multi-tenant

**Decision**: usar `rinosone` como schema principal da plataforma e reservar `rinosone_{tenantId}` para cada tenant futuro, onde `tenantId` é o `BIGINT UNSIGNED` estável do tenant. A primeira fase de acesso persiste exclusivamente no schema principal.

**Rationale**: o padrão isola dados de empresas futuras sem usar seu nome mutável como parte da infraestrutura. Mantém identidade e acesso como autoridade global, sem antecipar entidades, módulos ou provisionamento de tenants.

**Alternatives considered**: um único schema com coluna de tenant; schema baseado no nome da empresa; criação antecipada de schemas de tenant. Foram rejeitados por não atender ao isolamento aprovado, introduzir renomeações operacionais ou ampliar o escopo atual.

## Decision 10: Captura SMTP automatizada

**Decision**: usar Mailpit como servidor SMTP descartável em testes de integração e CI. Os testes consultam sua API para extrair o código e o link gerados, validar destinatário e conteúdo e concluir a jornada real pela API. Uma caixa postal externa não participa da suíte recorrente.

**Rationale**: valida a fronteira aplicação, fila e SMTP sem depender de pessoa, credenciais de e-mail ou variabilidade externa. A caixa externa permanece restrita a smoke tests ocasionais de homologação do provedor de envio.

**Alternatives considered**: confirmar manualmente cada mensagem; usar uma caixa externa em todo build; inspecionar somente `Mail::fake()`. Foram rejeitadas, respectivamente, por dependência humana, instabilidade externa e ausência de validação SMTP real.
