# Briefing do Projeto: Rinos One

**Data**: 2026-09-22  
**Status**: Confirmado  
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: uma plataforma que começará pela gestão de acesso de usuários.

**Problema que resolve**: estabelecer uma identidade única, validada por e-mail, para permitir acesso seguro à plataforma.

**Proposta de valor**: oferecer uma base de acesso simples e direta, apta a atender produtos e módulos futuros sem antecipar suas funcionalidades ou estruturas.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
| --- | --- | --- |
| Visitante | Pessoa sem acesso autenticado | Cadastrar-se, validar o e-mail e iniciar o acesso. |
| Usuário validado | Pessoa com acesso à plataforma | Autenticar-se por senha ou sem senha e encerrar sessões. |
| Equipe de infraestrutura | Administração inicial futura | Cadastrar administradores posteriormente. |

**Stakeholders de decisão**: não definidos neste briefing.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
| --- | --- | --- | --- | --- | --- |
| Web responsiva | Visitante e usuário validado | Navegadores modernos em desktop, tablet e telefone | MVP | Online | Única interface desta fase |
| API JSON versionada | Interface web e futuros consumidores autorizados | HTTP | MVP | Online | Fronteira estável para expansão futura |

**Restrições tecnológicas já obrigatórias**: PHP com Laravel no backend; Vue 3 e TypeScript na interface; MySQL; API JSON versionada; sessões mantidas no servidor.

## 4. Escopo

### MVP (Essencial)

1. Cadastro aberto com e-mail único.
2. Envio e controle da validação de e-mail.
3. Nome de exibição obrigatório após a validação.
4. Definição opcional de senha após a validação.
5. Login por e-mail e senha para usuários que tenham senha definida.
6. Login sem senha: envio simultâneo de link mágico e código de uso único por e-mail.
7. Continuidade do fluxo por código na página de origem ou por link em outra aba.
8. Encerramento da sessão atual e invalidação de todas as demais sessões do usuário.
9. Opção “Manter-me conectado” para persistir a autenticação entre sessões do navegador e reconstruir a sessão server-side quando necessário.

### Pós-MVP (Desejável)

Não definido deliberadamente nesta etapa.

### Fora de Escopo

- Perfil e edição de dados pessoais.
- Troca, recuperação ou exclusão de senha.
- Autenticação de dois fatores.
- Produtos e módulos da plataforma.
- Planejamento funcional dos produtos e módulos futuros.
- Entregáveis formais de privacidade, compliance e comunicação legal ao usuário.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: foco no necessário e direto para o acesso básico, sem burocracia ou estruturas extensas antes de serem necessárias.

**Decisões explícitas**:

- A plataforma deve preservar uma arquitetura preparada para produtos, módulos, interfaces e integrações futuras, sem implementá-los nesta fase.
- Produtos e módulos podem ser independentes, dependentes ou conectados entre si quando forem definidos futuramente.
- A arquitetura deve ser API-first mesmo com apenas uma interface web nesta fase.
- A senha é opcional; quem não a definir poderá autenticar-se somente pelo fluxo sem senha por e-mail.

## 6. Restrições

| Restrição | Valor | Notas |
| --- | --- | --- |
| Escopo | Acesso básico | Não incluir módulos ou soluções de produto nesta fase. |
| Técnica | Backend, interface, banco e API definidos | A organização deve manter separação entre domínio, API, interface e infraestrutura. |
| Configuração | Por ambiente | Arquivo-modelo comentado é versionado; valores reais não são versionados. |
| Limites de autenticação | Valores padrão seguros | Devem ser configuráveis por ambiente. |
| Provedor de e-mail | Já existe | Configuração concreta será definida na implantação. |
| Prazo | Não definido | — |
| Equipe | Não definida | — |
| Budget | Não definido | — |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Backend | PHP e Laravel | Tecnologia obrigatória para a plataforma. |
| Interface web | Vue 3 e TypeScript | Tecnologia obrigatória para a interface responsiva. |
| API | JSON versionada | Permite a separação da interface e futuras expansões. |
| Banco de dados | MySQL | Banco obrigatório da plataforma. |
| Sessões | Server-side | Requisito de segurança e controle de sessões. |
| E-mail | Provedor configurável por ambiente | Credenciais e parâmetros reais não devem ser versionados. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Validar formato e unicidade do e-mail.
- Armazenar senhas somente com hash forte.
- Exigir tamanho mínimo e requisitos de força da senha, sem consultar listas de senhas vazadas.
- Exigir senha com no mínimo 6 caracteres e pelo menos 2 destas 4 categorias: letra minúscula, letra maiúscula, caractere especial e número.
- Emitir links e códigos aleatórios, de uso único, com expiração de 10 minutos e invalidação das emissões anteriores.
- Limitar envios e tentativas por e-mail, IP e janela de tempo com valores padrão seguros configuráveis por ambiente.
- Não revelar publicamente se um e-mail possui cadastro.
- Usar cookies `HttpOnly`, `Secure` em produção e proteção contra CSRF.
- Não expirar sessões por inatividade; permitir autenticação persistente opcional, com parâmetros configuráveis por ambiente.
- Não registrar senhas, links, códigos, tokens ou dados pessoais desnecessários em logs.
- Reter logs de segurança por 30 dias por padrão, com período configurável por ambiente; excluir sem guarda emissões de e-mail usadas, vencidas ou substituídas e sessões invalidadas.
- Cobrir fluxos de sucesso e falha com testes automatizados, inclusive emissões inválidas, expiradas, reutilizadas e bloqueadas.
- Executar no CI formatação, testes de backend e frontend, verificação de tipos e build de produção.

**Compliance**: entregáveis formais de compliance e privacidade ficam adiados para fase futura; os controles técnicos de segurança aprovados permanecem no escopo atual.

## 9. Visão de Futuro

O planejamento de produtos e módulos para os próximos períodos foi explicitamente adiado. A evolução deve ser definida apenas quando houver necessidade concreta, preservando o foco desta primeira fase.

---

## Itens a Definir

| Item | Dimensão | Impacto |
| --- | --- | --- |
| Parâmetros do provedor de e-mail na implantação | Infraestrutura | Alto |
| Stakeholders de decisão, equipe, prazo e budget | Governança | Médio |
| Compliance e privacidade formais | Governança | Adiado para fase futura |

---

**Próximo passo recomendado**: `Dev Pipeline - 2. Discovery - Constitution` para definir princípios de governança.
