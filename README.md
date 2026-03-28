# LojaTecInfo — Sistema de Gestão de Pedidos

Sistema web para gerenciamento de pedidos e transportadoras, com importação massiva de CSV via pipeline assíncrono de alta performance.

**Stack:** Laravel 13 · PostgreSQL 17 · Redis · Nginx · PHP 8.5-FPM · Docker Compose

---

## Sumário

- [Início Rápido](#início-rápido)
- [Arquitetura dos Serviços Docker](#arquitetura-dos-serviços-docker)
- [Pipeline de Importação CSV](#pipeline-de-importação-csv)
- [Filas Redis](#filas-redis)
- [Auto-scaling de Daemons](#auto-scaling-de-daemons)
- [Auto-tuning do PostgreSQL](#auto-tuning-do-postgresql)
- [Acompanhamento em Tempo Real (SSE)](#acompanhamento-em-tempo-real-sse)
- [Ciclo de Status da Importação](#ciclo-de-status-da-importação)
- [Banco de Dados](#banco-de-dados)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Performance](#performance)

---

## Início Rápido

```bash
# Clone e configure
cp .env.example .env

# Suba os serviços
docker compose up -d

# Execute as migrations e seeders
docker compose exec app php artisan migrate --seed
```

Acesse em: `http://localhost:8080`

---

## Arquitetura dos Serviços Docker

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Compose                       │
│                                                         │
│  ┌─────────┐    ┌─────────-─┐    ┌───────────────────┐  │
│  │  nginx  │───▶│    app    │    │      worker       │  │
│  │  :8080  │    │  php-fpm  │    │  supervisord      │  │
│  └─────────┘    │   :9000   │    │  ├ import-reader  │  │
│                 └────-┬────-┘    │  ├ import-worker  │  │
│                       │          │  └ import-spool   │  │
│                  ┌────▼─────┐    └────────┬──────────┘  │
│                  │ postgres │             │             │
│                  │  :5432   │◀────────────┘             │
│                  └──────────┘                           │
│                  ┌──────────┐                           │
│                  │  redis   │◀── app + worker           │
│                  │  :6379   │                           │
│                  └──────────┘                           │
└─────────────────────────────────────────────────────────┘
```

| Serviço    | Imagem base            | Função                                    |
|------------|------------------------|-------------------------------------------|
| `nginx`    | nginx:alpine           | Reverse proxy, serve assets, upload limit |
| `app`      | php:8.5-fpm            | Servidor web Laravel via FastCGI          |
| `worker`   | php:8.5-cli            | Daemons de importação (supervisord)       |
| `postgres` | postgres:17            | Banco de dados com auto-tuning            |
| `redis`    | redis:7-alpine         | Filas de importação                       |

O `app` e o `worker` compartilham o volume `app_storage` (diretório `storage/`), o que permite que o worker escreva os arquivos de spool e o PostgreSQL os leia via `COPY FROM`.

---

## Pipeline de Importação CSV

A importação foi projetada para processar arquivos grandes (centenas de MB a vários GB) sem travar o servidor web. O fluxo é dividido em três estágios independentes:

```
Upload CSV
    │
    ▼
[Redis: importacao:fila]  ← ID da importação
    │
    ▼
┌─────────────────────────────────────────────────┐
│  Estágio 1 — ImportacaoReaderDaemon             │
│                                                 │
│  • Lê o CSV em chunks de 5.000 linhas           │
│  • Armazena cada chunk no Redis (TTL: 2h)       │
│  • Enfileira chave do chunk em importacao:work  │
│  • Incrementa total_linhas a cada chunk         │
│  • Ao final: status → Processando               │
│  • Empurra sentinela para importacao:spool      │
└─────────────────────────────────────────────────┘
    │ (paralelo — N workers simultâneos)
    ▼
[Redis: importacao:work]  ← "importacaoId:chunkN"
    │
    ▼
┌─────────────────────────────────────────────────┐
│  Estágio 2 — ImportacaoWorkerDaemon             │
│                                                 │
│  • Lê chunk do Redis                            │
│  • Valida cada linha (campos obrigatórios,      │
│    tipos, tamanhos)                             │
│  • Escreve CSV de spool em:                     │
│    storage/importacoes/spool/{id}/{chunk}.csv   │
│  • Permissão 0644 para o postgres ler           │
│  • Empurra metadados em importacao:spool        │
└─────────────────────────────────────────────────┘
    │
    ▼
[Redis: importacao:spool]  ← metadados do chunk
    │
    ▼
┌─────────────────────────────────────────────────┐
│  Estágio 3 — ImportacaoSpoolDaemon              │
│                                                 │
│  • Recebe metadados do chunk                    │
│  • Executa COPY INTO pedidos FROM 'arquivo.csv' │
│  • Incrementa linhas_processadas e linhas_erro  │
│  • Acumula até 100 amostras de erro (JSON)      │
│  • Remove arquivo de spool após COPY            │
│  • Sentinela: verifica se importação concluiu   │
└─────────────────────────────────────────────────┘
    │
    ▼
status → Concluido / Falhou
```

### Por que COPY ao invés de INSERT?

`COPY FROM file CSV` é 10–100× mais rápido do que INSERT em batch porque:
- Ignora o parser SQL linha a linha
- Gera WAL mínimo por linha
- Atualiza índices GIN em bulk ao final do arquivo (não a cada linha)

### Tratamento de falhas

Se um `COPY` falhar, dois arquivos de diagnóstico são gerados automaticamente:

```
storage/importacoes/debug/{id}_chunk{N}_{timestamp}.csv   ← dados que falharam
storage/importacoes/debug/{id}_chunk{N}_{timestamp}.sql   ← o COPY que falhou + mensagem de erro
```

---

## Filas Redis

| Fila                  | Tipo   | Produtor              | Consumidor               | Conteúdo                     |
|-----------------------|--------|-----------------------|--------------------------|------------------------------|
| `importacao:fila`     | List   | Controller (upload)   | ImportacaoReaderDaemon   | ID da importação (int)       |
| `importacao:work`     | List   | ImportacaoReaderDaemon| ImportacaoWorkerDaemon   | `"importacaoId:chunkN"`      |
| `importacao:spool`    | List   | ImportacaoWorkerDaemon| ImportacaoSpoolDaemon    | JSON com metadados do chunk  |
| `importacao:{id}:lote:{N}` | String | ImportacaoReaderDaemon | ImportacaoWorkerDaemon | JSON das linhas (TTL 2h) |

### Mensagem sentinela

Após o Reader terminar de ler o CSV completo, ele empurra uma mensagem especial na fila `importacao:spool` com `verificar_conclusao: true` e sem dados. Isso garante que o SpoolDaemon faça a verificação de conclusão mesmo que todos os chunks já tenham sido processados antes do Reader terminar de atualizar `total_linhas`.

Sem o sentinela, haveria uma race condition: o SpoolDaemon poderia processar 100% dos chunks antes de `total_linhas` ser definido, e nunca marcaria a importação como concluída.

---

## Auto-scaling de Daemons

O container `worker` detecta automaticamente os CPUs disponíveis no startup e calcula o número de processos para cada daemon:

| Daemon          | Fórmula               | Mínimo | Máximo |
|-----------------|-----------------------|--------|--------|
| `import-reader` | `CPUs / 4`            | 1      | 4      |
| `import-worker` | `CPUs / 2`            | 2      | 8      |
| `import-spool`  | `CPUs / 4`            | 1      | 2      |

Exemplos:

| CPUs | Readers | Workers | Spool |
|------|---------|---------|-------|
| 2    | 1       | 2       | 1     |
| 4    | 1       | 2       | 1     |
| 8    | 2       | 4       | 2     |
| 16   | 4       | 8       | 2     |

Para sobrescrever manualmente, defina as variáveis no `.env`:

```
QUEUE_READERS=2
QUEUE_WORKERS=4
QUEUE_SPOOL=1
```

### Importações paralelas

Múltiplas importações rodam em paralelo naturalmente. Como o Reader enfileira chunks um a um (não em lote), dois Readers rodando simultâneamente intercalam seus chunks na fila `importacao:work`. Os Workers e SpoolDaemons processam sem saber ou se importar de qual importação o chunk pertence.

---

## Auto-tuning do PostgreSQL

O script `docker/postgres/tune.sh` lê a RAM e CPUs disponíveis no container e passa flags `-c` ao `postgres` com valores calculados dinamicamente (estilo pgTune):

| Parâmetro                  | Fórmula                   | Finalidade                                    |
|----------------------------|---------------------------|-----------------------------------------------|
| `shared_buffers`           | RAM / 4                   | Cache de dados compartilhado                  |
| `effective_cache_size`     | RAM × 0.75                | Estimativa do cache total (SO + Postgres)     |
| `work_mem`                 | RAM / 200                 | Memória por operação de sort/hash             |
| `maintenance_work_mem`     | RAM / 8 (máx. 2GB)        | Para VACUUM, CREATE INDEX, etc.               |
| `max_parallel_workers`     | CPUs                      | Máximo de workers para consultas paralelas    |
| `max_parallel_workers_per_gather` | CPUs / 2          | Workers por nó de plano                       |
| `gin_pending_list_limit`   | 32MB                      | Reduz "travas" durante inserts em colunas GIN |
| `checkpoint_completion_target` | 0.9               | Suaviza escrita do checkpoint                 |
| `effective_io_concurrency` | 200                       | Para SSDs (I/O paralelo)                      |
| `random_page_cost`         | 1.1                       | Favorece index scans em SSD                   |

---

## Acompanhamento em Tempo Real (SSE)

A tela de progresso usa **Server-Sent Events** (SSE) ao invés de polling. O servidor mantém a conexão HTTP aberta e empurra atualizações a cada 1 segundo.

```
Navegador                                Laravel (php-fpm)
    │                                           │
    │── GET /pedidos/importar/{id}/progresso ──▶│
    │                                           │  Content-Type: text/event-stream
    │◀── data: {"status":"lendo",...} ─────-----│  (loop infinito)
    │◀── data: {"status":"processando",...}-----│
    │◀── data: {"status":"concluido",...} ─-----│  (fecha conexão)
    │                                           │
```

**Importante:** Cada conexão SSE ocupa um worker do PHP-FPM enquanto a importação está em andamento. Para importações longas (ex: 10M linhas ≈ 5–10 min), o worker fica bloqueado nesse tempo. Dimensione `pm.max_children` no PHP-FPM de acordo com o número de usuários simultâneos esperados.

O cabeçalho `X-Accel-Buffering: no` é enviado para desativar o buffer do Nginx e garantir que cada evento chegue ao navegador imediatamente.

---

## Ciclo de Status da Importação

```
                    ┌─────────-┐
        upload ───▶ │ Pendente │
                    └────┬────-┘
                         │ Reader pega da fila
                    ┌────▼────┐
                    │  Lendo  │  (Reader lendo o CSV)
                    └────┬────┘
                         │ Reader terminou, chunks na fila
                    ┌────▼────────┐
            ┌──────▶│ Processando │◀─────-─┐
            │       └────┬────────┘        │
            │            │                 │
     retomar│      pausa │          retomar│
            │       ┌────▼────┐            │
            └───────│ Pausada │───────────-┘
                    └─────────┘
                         │ (se não retomada)
                    ┌────▼────┐
                    │Cancelada│  (usuário cancelou)
                    └─────────┘

         Processando ──▶ Concluido  (tudo ok)
         Processando ──▶ Falhou     (erro fatal no COPY)
```

| Status       | Pode pausar | Pode retomar | Pode cancelar | Em andamento |
|--------------|:-----------:|:------------:|:-------------:|:------------:|
| Pendente     | —           | —            | sim           | sim          |
| Lendo        | —           | —            | sim           | sim          |
| Processando  | sim         | —            | sim           | sim          |
| Pausada      | —           | sim          | sim           | sim          |
| Concluido    | —           | —            | —             | —            |
| Cancelada    | —           | —            | —             | —            |
| Falhou       | —           | —            | —             | —            |

---

## Banco de Dados

### Tabela `pedidos`

| Coluna        | Tipo            | Notas                       |
|---------------|-----------------|-----------------------------|
| id            | bigserial PK    |                             |
| descricao     | varchar(120)    |                             |
| nomecliente   | varchar(100)    | índice GIN trigram (ILIKE)  |
| produto       | varchar(70)     |                             |
| preco         | decimal(10,2)   |                             |
| quantidade    | integer         |                             |
| total         | decimal(10,2)   |                             |
| created_at    | timestamp       | índice B-tree               |
| updated_at    | timestamp       |                             |

### Tabela `importacoes`

| Coluna              | Tipo         | Notas                              |
|---------------------|--------------|------------------------------------|
| id                  | bigserial PK |                                    |
| arquivo_original    | varchar      |                                    |
| caminho             | varchar      | path no storage local              |
| total_linhas        | integer      | 0 durante fase Lendo               |
| linhas_processadas  | integer      |                                    |
| linhas_com_erro     | integer      |                                    |
| status              | varchar      | enum StatusImportacao              |
| amostra_erros       | json         | até 100 exemplos de erros          |
| iniciado_em         | timestamp    |                                    |
| concluido_em        | timestamp    |                                    |

### Índices de performance

O projeto usa a extensão `pg_trgm` do PostgreSQL para criar índices GIN que aceleram buscas `ILIKE '%texto%'`:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX idx_pedidos_nomecliente_trgm ON pedidos USING GIN (nomecliente gin_trgm_ops);
```

---

## Variáveis de Ambiente

| Variável           | Padrão     | Descrição                                      |
|--------------------|------------|------------------------------------------------|
| `DB_HOST`          | postgres   | Host do PostgreSQL                             |
| `DB_PORT`          | 5432       | Porta do PostgreSQL                            |
| `DB_DATABASE`      | lojatecinfo| Nome do banco                                  |
| `DB_USERNAME`      | lojatecinfo| Usuário do banco                               |
| `DB_PASSWORD`      | —          | Senha do banco                                 |
| `REDIS_HOST`       | redis      | Host do Redis                                  |
| `REDIS_PORT`       | 6379       | Porta do Redis                                 |
| `QUEUE_READERS`    | (auto)     | Nº de processos import-reader (0 = auto)       |
| `QUEUE_WORKERS`    | (auto)     | Nº de processos import-worker (0 = auto)       |
| `QUEUE_SPOOL`      | (auto)     | Nº de processos import-spool (0 = auto)        |

---

## Performance

### Limites de upload

Três camadas precisam estar alinhadas para aceitar arquivos grandes:

| Camada           | Configuração                    | Arquivo                          |
|------------------|---------------------------------|----------------------------------|
| Nginx            | `client_max_body_size 1G`       | `docker/nginx/default.conf`      |
| PHP              | `upload_max_filesize = 1G`      | `docker/php/php.ini`             |
| PHP              | `post_max_size = 1G`            | `docker/php/php.ini`             |
| Laravel          | `max:1048576` (KB)              | `ImportacaoPedidosController.php`|

### Throughput esperado

| Arquivo   | Linhas      | Tempo aproximado* |
|-----------|-------------|-------------------|
| 50 MB     | ~500k       | ~30s              |
| 200 MB    | ~2M         | ~2min             |
| 700 MB    | ~7M         | ~6min             |

\* Em hardware com 4 CPUs / 8GB RAM. Varia conforme disco, configuração de PostgreSQL e número de erros de validação.

### Gargalos conhecidos

- **GIN pending list:** inserts muito rápidos podem acumular o pending list do índice GIN e causar pausas periódicas. Mitigado com `gin_pending_list_limit=32MB` e o uso de `COPY` (que atualiza o índice em bulk).
- **Workers SSE vs PHP-FPM:** cada usuário na tela de progresso ocupa um worker FPM. Em ambientes com muitos usuários simultâneos, aumente `pm.max_children`.
- **Múltiplas importações:** o sistema suporta várias importações simultâneas, mas readers adicionais aumentam I/O de leitura de disco e pressão sobre o Redis. O padrão automático limita a 4 readers para evitar degradação.
