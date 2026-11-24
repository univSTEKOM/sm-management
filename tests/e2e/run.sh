#!/bin/sh
# Runs the Playwright suite inside the official Playwright image (browser
# system libraries included) on the compose network, against the local app.
# The container name is used instead of "app": Chrome forces HTTPS for the
# HSTS-preloaded ".app" name.
# Local development only: the database is reseeded first.
set -e
cd "$(dirname "$0")/../.."
DOCKER="${DOCKER_BIN:-/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe}"
PW_VERSION="$(node -p "require('@playwright/test/package.json').version")"

"$DOCKER" compose exec -T -u 1000:1000 -e HOME=/tmp app php artisan migrate:fresh --seed --force

"$DOCKER" run --rm \
    --network sm-management_default \
    -v "$(wslpath -w "$PWD"):/work" -w /work \
    -e E2E_BASE_URL=http://sm-management-app-1:8000 -e E2E_SKIP_RESEED=1 -e CI=1 \
    "mcr.microsoft.com/playwright:v${PW_VERSION}-noble" \
    npx playwright test "$@"
