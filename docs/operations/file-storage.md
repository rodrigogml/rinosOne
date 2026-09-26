# Operação — Fundação de armazenamento de arquivos

Este documento orienta a implantação e a operação da fundação privada de arquivos. Ela não cria drive, URLs públicas, compartilhamento externo ou interface de administração de volumes.

## Configuração por ambiente

Defina os valores reais somente no `.env` do ambiente. O arquivo [`.env.example`](../../.env.example) contém os valores de referência e comentários.

| Grupo | Variáveis | Finalidade |
| --- | --- | --- |
| Backend padrão | `FILE_STORAGE_DEFAULT_BACKEND`, `FILE_STORAGE_LOCAL_PRIVATE_DISK`, `FILE_STORAGE_LOCAL_PRIVATE_PATH`, `FILE_STORAGE_LOCAL_PRIVATE_STATE` | Seleciona o disco privado inicial. O diretório não pode ser publicado pelo servidor web. |
| Backends adicionais | `FILE_STORAGE_BACKEND_DEFINITIONS` | Declara novos pontos de montagem por chave, sem mover objetos existentes. |
| Retenção | `FILE_TRASH_RETENTION_DAYS`, `FILE_BACKUP_RETENTION_DAYS`, `FILE_TECHNICAL_RETENTION_DAYS`, `FILE_ORPHAN_RETENTION_DAYS` | Define lixeira, recuperação de backup, retenção física e segurança para órfãos. A retenção técnica deve ser igual ou maior que a de backup. |
| Compactação | `FILE_COMPRESSION_RULES`, `FILE_COMPRESSION_REPROCESS_INTERVAL_MINUTES` | Seleciona MIME types/extensões para `GZIP` e agenda reprocessamento. Só há promoção quando há ganho de espaço. |
| Manutenção | `FILE_STORAGE_RETENTION_PURGE_INTERVAL_MINUTES`, `FILE_STORAGE_RECONCILIATION_INTERVAL_MINUTES` | Define a periodicidade dos expurgos e da reconciliação. |
| Logs técnicos | `FILE_STORAGE_LOG_LEVEL`, `FILE_STORAGE_LOG_RETENTION_DAYS` | Controla o canal `file-storage`, mantido separado do log geral. |

> [!IMPORTANT]
> Não use disco público, link simbólico público, URL direta nem armazenamento de segredo em `FILE_STORAGE_BACKEND_DEFINITIONS`. Caminhos físicos, hashes, nomes, bytes e credenciais não devem ser incluídos em tickets, evidências ou logs.

## Aplicação e processos

Após atualizar a aplicação e o ambiente, execute as migrations globais antes de expor consumidores da fundação:

```sh
php artisan migrate:global --force
php artisan config:cache
php artisan queue:restart
```

Mantenha ao menos um worker supervisionado para os jobs de expurgo, reconciliação e compactação:

```sh
php artisan queue:work database --sleep=1 --tries=3 --max-time=3600
```

O scheduler do host deve chamar o Laravel a cada minuto:

```sh
php artisan schedule:run
```

Em homologação local, pode ser usado:

```sh
php artisan schedule:work
```

Valide os agendamentos carregados sem executar rotinas destrutivas:

```sh
php artisan schedule:list
```

## Ciclo de manutenção

| Rotina | Resultado seguro |
| --- | --- |
| Expurgo de lixeira | Libera somente posses de workspace cujo `purgeAfter` venceu. A quota permanece consumida até a liberação. |
| Limpeza de versões e objetos | Remove dados somente sem referências válidas e após a maior retenção entre backup e técnica. |
| Reconciliação | Marca referências quebradas ou escritas antigas como `ORPHANED`; remove objetos físicos sem catálogo apenas depois da retenção de órfãos. |
| Reprocessamento | Troca `IDENTITY` e `GZIP` apenas após validar integridade e mantém a representação substituída em retenção. |

## Diagnóstico e recuperação

| Situação | Ação segura |
| --- | --- |
| Backend não aceita escrita | Verificar permissões, espaço e configuração do disco privado; restaurar o backend e reiniciar o worker. Não recriar manualmente registros ou objetos. |
| Objeto ou representação inconsistente | Consultar o canal técnico `storage/logs/file-storage-*.log`, restaurar a disponibilidade do backend e permitir a reconciliação agendada. |
| Scheduler parado | Restaurar o agendamento; os prazos lógicos continuam sendo validados pelas operações, e a limpeza será retomada no próximo ciclo. |
| Restauração de banco | Restaurar também os volumes privados compatíveis com `FILE_BACKUP_RETENTION_DAYS`. Nunca reduza a retenção técnica antes de expirar a janela de recuperação de backup. |

> [!WARNING]
> Não exclua manualmente arquivos em `storage/app/file-storage` ou no ponto de montagem configurado. A fundação usa catálogo, deduplicação e retenção; exclusão manual gera referências quebradas e deve ser tratada pela reconciliação.

## Verificação antes da liberação

- [ ] Backend privado gravável e inacessível pelo servidor web.
- [ ] `FILE_TECHNICAL_RETENTION_DAYS >= FILE_BACKUP_RETENTION_DAYS`.
- [ ] Worker e scheduler supervisionados.
- [ ] `php artisan schedule:list` apresenta expurgo, reconciliação e reprocessamento.
- [ ] `php artisan test`, `npm run type-check`, `npm test` e `npm run build` concluídos.
- [ ] Nenhum valor real de caminho, credencial ou configuração de volume foi versionado.
