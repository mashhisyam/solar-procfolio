#!/bin/sh
# Backup harian PostgreSQL. Pasang di cron host:
#   30 1 * * * /opt/procfolio/docker/backup.sh >> /var/log/procfolio-backup.log 2>&1
set -eu
DIR="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${BACKUP_DIR:-$DIR/docker/backup}"
KEEP="${BACKUP_KEEP_DAYS:-30}"
STAMP=$(date +%Y%m%d-%H%M)
mkdir -p "$OUT"
cd "$DIR"
docker compose exec -T db pg_dump -U "${DB_USERNAME:-procfolio}" "${DB_DATABASE:-procfolio}" | gzip > "$OUT/procfolio-$STAMP.sql.gz"
find "$OUT" -name 'procfolio-*.sql.gz' -mtime +"$KEEP" -delete
echo "$(date -Is) backup selesai: procfolio-$STAMP.sql.gz"
