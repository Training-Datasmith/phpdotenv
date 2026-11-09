#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_TAG="phpdotenv-phpstan:8.5"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_cmd build -f "${REPO_ROOT}/docker/phpstan/Dockerfile" -t "${IMAGE_TAG}" "${REPO_ROOT}"

docker_cmd run --rm \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    -e COMPOSER_ALLOW_SUPERUSER=1 \
    "${IMAGE_TAG}" \
    bash -lc '
set -euo pipefail
git config --global --add safe.directory /app >/dev/null 2>&1 || true
COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress
php /tmp/composer.phar bin phpstan update --no-interaction --prefer-dist --no-progress
php /tmp/composer.phar audit --no-interaction --abandoned=report
out="$(mktemp)"
php vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G --no-progress --no-ansi >"${out}" 2>&1
cat "${out}"
if ! grep -F "[OK] No errors" "${out}" >/dev/null; then
    exit 1
fi
'

echo 'phpstan system-test: passed.'
