# Pesquisa técnica — Fundação de armazenamento de arquivos

## Contexto verificado

- Aplicação: Laravel 12, PHP 8.2+ (ambiente local atualmente em PHP 8.5) e MySQL 9.
- O armazenamento padrão do Laravel já é privado em `storage/app/private`; a fundação precisará de um disco privado próprio e configurável, sem expor objetos pela pasta pública.
- O catálogo principal reside no schema global `rinosone`. Schemas de tenants podem referenciá-lo, mas o catálogo global não dependerá deles.
- O runtime atual possui `fileinfo`, mas não `gd`, `imagick` ou `exif`.

## Decisões e justificativas

### 1. Catálogo global e independência de módulos

O catálogo de arquivos, conteúdos, versões, posses e retenção fica no schema principal. Módulos pessoais e de tenant guardarão apenas a referência estável à posse ou à versão que consomem. Isso permite uma mesma fundação para perfil, drive, álbuns e documentos, sem criar dependências entre schemas de tenants.

### 2. Deduplicação por conteúdo, não por arquivo lógico

`file_file` representa a linhagem lógica. `file_fileVersion` preserva a árvore de versões. O conteúdo lógico é identificado pelo SHA-256 dos bytes originais e associado a um único `file_fileContent`. Assim, duas posses podem apontar para os mesmos bytes sem compartilhar a própria linhagem nem o estado de lixeira.

O objeto físico é separado em `file_storageObject`. Seu nome usa a hash do conteúdo armazenado, jamais o UUID de arquivo ou de versão: um mesmo objeto pode atender muitas versões. A árvore física será fragmentada por prefixos da hash, por exemplo `objects/sha256/ab/cd/<storedHash>.blob`.

### 3. Backends são configurados fora do banco

O banco cataloga a chave estável do backend e sua disponibilidade; caminhos, credenciais e discos Laravel pertencem a configuração de implantação. Entrar um novo volume não exige mover objetos existentes: a política de escrita passa a escolhê-lo apenas para novos objetos.

### 4. Compressão técnica é opcional e reversível

As regras serão configuráveis por MIME type e extensão. O serviço só promoverá uma representação compactada quando ela for menor que a original. Imagens e vídeos já comprimidos não serão recomprimidos na política inicial. A alteração posterior de política gera uma tarefa assíncrona de reprocessamento e troca segura de objetos, preservando a retenção necessária para recuperação.

### 5. Recorte de avatar precisa de processamento confiável no servidor

A prévia no navegador melhora a experiência, mas não é autoridade: coordenadas, tipo, tamanho e dimensões serão validados no servidor. A saída será sempre 400 × 400 px e a origem transitória será descartada. A implementação exige a extensão GD com suporte a JPEG, PNG e WebP; ela é pré-requisito explícito de implantação.

### 6. Retenção e limpeza são assíncronas

Datas de elegibilidade são gravadas em transação; processos agendados fazem a exclusão física depois do maior prazo entre retenção técnica e backups. Falhas entre escrita física e persistência são tratadas como objetos órfãos e reconciliadas por varredura segura, sem remover objetos recentes.
