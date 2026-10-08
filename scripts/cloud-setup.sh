#!/usr/bin/env bash
# Prepares a Claude Code cloud session. Does nothing outside the cloud.

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

set -euo pipefail

cd "${CLAUDE_PROJECT_DIR:?CLAUDE_PROJECT_DIR is not set}"

export COMPOSER_ALLOW_SUPERUSER=1

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=..*' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ ! -d public/build ]; then
    npm ci
    npm run build
fi
