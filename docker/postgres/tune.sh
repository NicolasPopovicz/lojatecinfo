#!/bin/bash
set -e

# ── Detecta recursos disponíveis ──────────────────────────────────────────────
RAM_KB=$(grep MemTotal /proc/meminfo | awk '{print $2}')
RAM_MB=$(( RAM_KB / 1024 ))
CPUS=$(nproc --all)

# ── Funções auxiliares ────────────────────────────────────────────────────────
clamp() {
    local val=$1 min=$2 max=${3:-}
    [ "$val" -lt "$min" ] && val=$min
    [ -n "$max" ] && [ "$val" -gt "$max" ] && val=$max
    echo "$val"
}

# ── Calcula parâmetros (estilo pgTune) ────────────────────────────────────────
# shared_buffers: 25% da RAM — cache de dados na memória do Postgres
SHARED_BUFFERS=$(clamp $(( RAM_MB / 4 )) 128 8192)

# effective_cache_size: 75% da RAM — estimativa para o planner de quanto a
# RAM total (SO + Postgres) pode cachear; afeta o custo de index scans
EFFECTIVE_CACHE=$(clamp $(( RAM_MB * 3 / 4 )) 256)

# work_mem: memória por operação de sort/hash; cuidado com paralelismo —
# múltiplos workers podem usar este valor simultaneamente
WORK_MEM=$(clamp $(( RAM_MB / 200 )) 4 512)

# maintenance_work_mem: VACUUM, CREATE INDEX, ALTER TABLE — pode ser bem maior
MAINTENANCE_WORK_MEM=$(clamp $(( RAM_MB / 8 )) 64 2048)

# Paralelismo: usa todos os núcleos disponíveis, mas limita gather a metade
MAX_PARALLEL_WORKERS=$CPUS
MAX_PARALLEL_PER_GATHER=$(clamp $(( CPUS / 2 )) 1 $(( CPUS - 1 )))
MAX_PARALLEL_MAINTENANCE=$(clamp $(( CPUS / 2 )) 1 4)

echo "==> [postgres-tune] RAM: ${RAM_MB}MB | CPUs: ${CPUS}"
echo "==> [postgres-tune] shared_buffers=${SHARED_BUFFERS}MB | effective_cache_size=${EFFECTIVE_CACHE}MB"
echo "==> [postgres-tune] work_mem=${WORK_MEM}MB | maintenance_work_mem=${MAINTENANCE_WORK_MEM}MB"
echo "==> [postgres-tune] max_parallel_workers=${MAX_PARALLEL_WORKERS} | per_gather=${MAX_PARALLEL_PER_GATHER}"

# ── Delega para o entrypoint original do postgres com -c flags ────────────────
exec docker-entrypoint.sh "$@" \
    -c "shared_buffers=${SHARED_BUFFERS}MB" \
    -c "effective_cache_size=${EFFECTIVE_CACHE}MB" \
    -c "work_mem=${WORK_MEM}MB" \
    -c "maintenance_work_mem=${MAINTENANCE_WORK_MEM}MB" \
    -c "checkpoint_completion_target=0.9" \
    -c "wal_buffers=16MB" \
    -c "min_wal_size=1GB" \
    -c "max_wal_size=4GB" \
    -c "random_page_cost=1.1" \
    -c "effective_io_concurrency=200" \
    -c "default_statistics_target=100" \
    -c "gin_pending_list_limit=32MB" \
    -c "max_worker_processes=${CPUS}" \
    -c "max_parallel_workers=${MAX_PARALLEL_WORKERS}" \
    -c "max_parallel_workers_per_gather=${MAX_PARALLEL_PER_GATHER}" \
    -c "max_parallel_maintenance_workers=${MAX_PARALLEL_MAINTENANCE}"
