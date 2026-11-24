#!/bin/sh
# Compares installed Composer packages in the host vendor/ and the container vendor volume.
# Exit code 1 when they differ. See AGENTS.md "Sinkronisasi Composer".
set -eu

cd "$(dirname "$0")/.."
DOCKER="${DOCKER:-docker}"
"$DOCKER" version >/dev/null 2>&1 || DOCKER="/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

composer show 2>/dev/null | awk '{print $1, $2}' > "$tmp/host"
"$DOCKER" compose exec -T -u 1000:1000 -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer app composer show 2>/dev/null | awk '{print $1, $2}' > "$tmp/container"

if diff -u "$tmp/host" "$tmp/container"; then
    echo "vendor in sync ($(wc -l < "$tmp/host") packages)"
else
    echo "vendor OUT OF SYNC: run 'composer install --ignore-platform-reqs' on the host or 'composer install' in the container" >&2
    exit 1
fi
