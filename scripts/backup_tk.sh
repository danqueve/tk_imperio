#!/bin/sh
# Backup de TK Imperio: base tk_imperio + carpeta uploads/ en un directorio de destino.
# Uso:  sh scripts/backup_tk.sh /ruta/destino
# Las credenciales de MySQL se leen de ~/.my.cnf (sección [client]); no van en este script.
# Después, el script diario de rclone que ya corre en el VPS sube /ruta/destino.
set -eu

DESTINO="${1:?Falta el directorio de destino}"
BASE="${TK_DB:-tk_imperio}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
FECHA="$(date +%Y%m%d_%H%M%S)"

mkdir -p "$DESTINO"
mysqldump --single-transaction --routines --default-character-set=utf8mb4 "$BASE" | gzip > "$DESTINO/tk_db_$FECHA.sql.gz"
tar -czf "$DESTINO/tk_uploads_$FECHA.tar.gz" -C "$RAIZ" uploads

# Conserva los últimos 14 días.
find "$DESTINO" -maxdepth 1 \( -name 'tk_db_*.sql.gz' -o -name 'tk_uploads_*.tar.gz' \) -mtime +14 -delete

echo "Backup listo en $DESTINO ($FECHA)"
