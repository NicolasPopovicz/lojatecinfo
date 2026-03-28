#!/bin/bash
set -e

# ── Auto-tune: calcula readers/workers a partir dos núcleos disponíveis ───────
# Só aplica se a variável não foi definida explicitamente no ambiente.
CPUS=$(nproc --all)

if [ -z "${QUEUE_READERS}" ]; then
    # 1 reader por grupo de 4 núcleos; mínimo 1, máximo 4
    QUEUE_READERS=$(( CPUS / 4 ))
    [ "$QUEUE_READERS" -lt 1 ] && QUEUE_READERS=1
    [ "$QUEUE_READERS" -gt 4 ] && QUEUE_READERS=4
    export QUEUE_READERS
fi

if [ -z "${QUEUE_WORKERS}" ]; then
    # 1 worker por grupo de 2 núcleos; mínimo 2, máximo 8
    QUEUE_WORKERS=$(( CPUS / 2 ))
    [ "$QUEUE_WORKERS" -lt 2 ] && QUEUE_WORKERS=2
    [ "$QUEUE_WORKERS" -gt 8 ] && QUEUE_WORKERS=8
    export QUEUE_WORKERS
fi

if [ -z "${QUEUE_SPOOL}" ]; then
    # Spool daemon é I/O + DB; 1-2 processos são suficientes
    QUEUE_SPOOL=$(( CPUS / 4 ))
    [ "$QUEUE_SPOOL" -lt 1 ] && QUEUE_SPOOL=1
    [ "$QUEUE_SPOOL" -gt 2 ] && QUEUE_SPOOL=2
    export QUEUE_SPOOL
fi

echo "==> [worker] CPUs detectadas: ${CPUS} | readers: ${QUEUE_READERS} | workers: ${QUEUE_WORKERS} | spool: ${QUEUE_SPOOL}"

# ── Aguarda banco ─────────────────────────────────────────────────────────────
echo "==> [worker] Aguardando banco de dados..."
until php artisan db:monitor 2>/dev/null; do
    sleep 2
done

echo "==> [worker] Corrigindo permissões de storage..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "==> [worker] Sincronizando configurações..."
php artisan config:clear
php artisan config:cache

exec supervisord -c /etc/supervisor/conf.d/queue.conf
