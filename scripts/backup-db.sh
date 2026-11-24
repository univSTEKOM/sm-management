#!/bin/sh
# Daily PostgreSQL backup (TD-06). Run from cron on the server, e.g.:
#   15 1 * * * /srv/sm-management/scripts/backup-db.sh
# Connection comes from the standard libpq variables (PGHOST, PGUSER, PGPASSWORD, PGDATABASE);
# keep them in a root-only env file, never in this repository.
# BACKUP_DIR      target directory (default /var/backups/sm-management)
# BACKUP_KEEP     days to keep (default 30)
# BACKUP_GPG_KEY  optional recipient: encrypts the dump (audit data is sensitive, S-07)
set -eu
DIR="${BACKUP_DIR:-/var/backups/sm-management}"
KEEP="${BACKUP_KEEP:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"
umask 077
mkdir -p "$DIR"
OUT="$DIR/sm-$STAMP.dump"

pg_dump --format=custom --no-owner --file="$OUT"

if [ -n "${BACKUP_GPG_KEY:-}" ]; then
    gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_KEY" --output "$OUT.gpg" "$OUT"
    rm -f "$OUT"
    OUT="$OUT.gpg"
fi

# A dump that cannot be listed is not a backup.
case "$OUT" in
    *.gpg) : ;;
    *) pg_restore --list "$OUT" > /dev/null ;;
esac

find "$DIR" -name 'sm-*.dump*' -mtime +"$KEEP" -delete
echo "backup ok: $OUT"
