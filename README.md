<p align="center">
  <img src="public/assets/brand/logo-768.png" width="480" alt="Rinos One">
</p>

# Rinos One

Rinos One é uma plataforma modular para pessoas e organizações. Ela fornece uma base única de identidade, acesso, contexto organizacional e experiência de trabalho para que produtos independentes ou conectados possam evoluir sem misturar suas regras de negócio.

A aplicação começa pela web responsiva, mas as capacidades de domínio são expostas por API JSON versionada em `/api/v1`, preservando a expansão futura para integrações e outras interfaces.

## Estado atual

As fundações já entregues ou em evolução no repositório incluem:

- cadastro, validação de e-mail, autenticação por senha ou sem senha e gestão de sessões;
- área autenticada responsiva, i18n, preferências visuais e sistema de design baseado em tokens;
- contexto pessoal permanente e contexto de organização (tenant) por aba;
- provisionamento isolado de schemas por tenant;
- perfil de usuário e avatar privado;
- fundação de armazenamento privado de arquivos, com catálogo, versões, deduplicação, retenção e compactação configurável;
- Rinos Drive Pessoal e Rinos Drive Work, com pastas, upload múltiplo, download privado, lixeira e exportações ZIP temporárias;
- fundação de autorização e superfícies administrativas em desenvolvimento;
- central de manutenções e catálogos globais, conforme capacidades autorizadas.

> [!IMPORTANT]
> Esta lista descreve fundações de plataforma, não um catálogo de produtos. Módulos de negócio só são adicionados quando possuem escopo e especificação aprovados.

## Arquitetura em resumo

| Camada | Tecnologia e responsabilidade |
| --- | --- |
| Aplicação e API | PHP 8.2+, Laravel 12 e API JSON versionada |
| Interface web | Vue 3, TypeScript, Pinia, Vue I18n e Vite |
| Dados | MySQL 9, sessões e filas persistidas no banco |
| Organização | Monólito modular: domínio e regras no backend; componentes e estado de interface no frontend |
| Tenancy | Schema global `rinosone` e schemas isolados `rinosone_{tenantId}` |
| Arquivos | Backends privados, sem URLs físicas diretas; referências e retenção controladas pelo banco |

As decisões de arquitetura, os limites de cada camada e as regras de evolução estão na [Constituição do Rinos One](docs/constitution.md).

## Requisitos locais

- PHP 8.2 ou superior e Composer;
- Node.js e npm;
- MySQL 9 ou compatível;
- um serviço de fila e scheduler para executar fluxos assíncronos em ambiente integrado;
- SMTP ou Mailpit para validar e-mails fora do modo `log`.

## Início rápido

1. Instale as dependências e crie sua configuração local:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   npm ci
   ```

2. Configure o banco e demais serviços em `.env`. Use somente valores locais; o arquivo modelo possui comentários para cada grupo de configuração.

3. Crie o schema global configurado e aplique as migrations:

   ```powershell
   php artisan migrate:global
   ```

4. Inicie os processos de desenvolvimento. Em terminais separados, ou pelo atalho integrado:

   ```powershell
   composer run dev
   ```

   O comando integrado inicia web, Vite, worker de fila e logs. Quando os processos forem iniciados separadamente, mantenha pelo menos:

   ```powershell
   php artisan serve
   npm run dev
   php artisan queue:work database --sleep=1 --tries=3
   php artisan schedule:work
   ```

> [!WARNING]
> Não versione `.env`, credenciais, chaves, senhas SMTP, caminhos privados ou dados de produção. As credenciais de runtime, migrations e provisionamento de tenant devem ser distintas.

## Qualidade

Antes de entregar uma alteração, execute as verificações adequadas ao escopo:

```powershell
php artisan test
npm test
npm run type-check
npm run build
```

Os testes de integração que dependem de ambiente adicional podem requerer a configuração indicada nos respectivos SDDs e documentos operacionais.

## Configuração e operação

O arquivo [`.env.example`](.env.example) é a referência versionada para variáveis de ambiente e políticas configuráveis. Para detalhes de implantação, processos supervisionados, permissões de banco e recuperação, consulte:

- [Acesso e implantação](docs/operations/access-deployment.md)
- [Provisionamento de tenants](docs/operations/tenant-provisioning.md)
- [Fundação de armazenamento de arquivos](docs/operations/file-storage.md)
- [Topologia de dados multi-tenant](docs/architecture/database-topology.md)

## Documentação de desenvolvimento

| Assunto | Referência |
| --- | --- |
| Princípios, governança e limites de escopo | [Constituição](docs/constitution.md) |
| Superfícies web, API e decisões de experiência | [Arquitetura de superfícies](docs/architecture/interaction-surfaces.md) |
| Tokens, identidade visual e ícones raster | [Catálogo de ícones e padrão visual](docs/architecture/icon-catalog.md) |
| Requisitos, planos, contratos e tarefas por capacidade | [`docs/specs/`](docs/specs/) |
| Contexto e decisões de descoberta | [`docs/briefing/`](docs/briefing/) |
| Operação por subsistema | [`docs/operations/`](docs/operations/) |

Cada diretório em `docs/specs/` representa uma capacidade delimitada. Quando houver, leia na ordem: `spec.md`, `plan.md`, contratos/interfaces, checklist e `tasks.md`.

## Como contribuir

1. Leia a Constituição e a documentação da capacidade afetada antes de alterar código.
2. Mantenha regras de negócio fora da interface e exponha capacidades pela API versionada.
3. Não antecipe módulos, entidades ou abstrações fora do escopo aprovado.
4. Atualize documentação e testes junto com mudanças de comportamento, contrato ou decisão técnica.
5. Preserve alterações locais de outras frentes e faça commits pequenos, claros e focados.

Relate vulnerabilidades e informações sensíveis somente por canal privado apropriado. Nunca as registre em issues, commits, logs ou documentação pública.
