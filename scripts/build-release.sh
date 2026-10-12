#!/usr/bin/env bash
# Build a production release zip for upload to cPanel (Step 15 of docs/decisions.md).
#
# Installs packages without development ones (so Boost, Pest and other dev tools
# are absent), installs the exact npm packages from package-lock.json and builds
# the front-end assets, and zips the app without secrets,
# tests, docs, local runtime files or the Vite dev-server marker. The dev install
# is always restored afterwards. Run from anywhere: bash scripts/build-release.sh
set -euo pipefail

cd "$(dirname "$0")/.."

for command in git composer npm zip unzip; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing the $command command; install it and run the script again." >&2
        exit 1
    fi
done

if [ -n "$(git status --porcelain)" ]; then
    echo "Warning: the working tree has uncommitted changes; they will be included in the release." >&2
fi

commit="$(git rev-parse --short HEAD)"
release_dir="release"
zip_path="$release_dir/laravel-blog-$commit.zip"

restore_dev_packages() {
    echo "Restoring development packages..."
    composer install --no-interaction --quiet
}
trap restore_dev_packages EXIT

echo "Installing production packages only..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "Installing npm packages from package-lock.json..."
npm ci

echo "Building front-end assets..."
npm run build

mkdir -p "$release_dir"
rm -f "$zip_path"

echo "Zipping $zip_path..."
zip -rq "$zip_path" . \
    -x '.env*' \
    -x '.git/*' '.github/*' \
    -x 'node_modules/*' \
    -x 'tests/*' 'docs/*' 'scripts/*' "$release_dir/*" \
    -x '.claude/*' '.ai/*' '.codex/*' '.cursor/*' '.idea/*' '.vscode/*' \
    -x 'CLAUDE.md' 'AGENTS.md' 'boost.json' 'phpunit.xml' '.phpunit*' '.phpactor.json' \
    -x 'public/hot' \
    -x 'storage/logs/*.log' \
    -x 'storage/framework/cache/data/*' \
    -x 'storage/framework/sessions/*' \
    -x 'storage/framework/views/*.php' \
    -x 'storage/framework/testing/*' \
    -x 'storage/pail/*' \
    -x 'bootstrap/cache/*.php'

# Keep the empty runtime folders' .gitignore files and add the safe .env template back.
zip -q "$zip_path" storage/framework/cache/data/.gitignore storage/framework/sessions/.gitignore storage/framework/views/.gitignore .env.example

echo "Checking the release..."
contents="$(unzip -Z1 "$zip_path")"
problems=0

for forbidden in '^\.env$' '^\.env\.(backup|production|testing|local)$' '^vendor/laravel/boost/' '^public/hot$' '^tests/' '^\.git/' '^node_modules/'; do
    if grep -Eq "$forbidden" <<<"$contents"; then
        echo "Release check failed: found $forbidden" >&2
        problems=1
    fi
done

if ! grep -q '^public/build/manifest.json$' <<<"$contents"; then
    echo "Release check failed: public/build/manifest.json is missing" >&2
    problems=1
fi

if [ "$problems" -ne 0 ]; then
    rm -f "$zip_path"
    exit 1
fi

echo "Release ready: $zip_path ($(du -h "$zip_path" | cut -f1))"
