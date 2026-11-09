#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FLOOR_IMAGE="phpdotenv-system-test:7.2"
PHPSTAN_IMAGE="phpdotenv-phpstan:8.5"
COMPOSER_BIN_IMAGE="php:8.4-cli@sha256:d63e8b80cd4c6cfd01cfa7c910522e299cdc7ca1a2b6d6563db48df3c17e6c55"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_cmd build -f "${REPO_ROOT}/docker/phpstan/Dockerfile" -t "${PHPSTAN_IMAGE}" "${REPO_ROOT}"

PHPSTAN_WORK="$(mktemp -d)"
cleanup() {
    if [[ -d "${PHPSTAN_WORK}" ]]; then
        if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
            sudo rm -rf "${PHPSTAN_WORK}"
        else
            rm -rf "${PHPSTAN_WORK}"
        fi
    fi
}
trap cleanup EXIT

cp -a "${REPO_ROOT}/." "${PHPSTAN_WORK}/"
rm -rf "${PHPSTAN_WORK}/vendor" 2>/dev/null || sudo rm -rf "${PHPSTAN_WORK}/vendor"

docker_cmd run --rm \
    -v "${PHPSTAN_WORK}:/app" \
    -v "${REPO_ROOT}/scripts/gate-output.sh:/opt/gate-output.sh:ro" \
    -w /app \
    -e COMPOSER_ALLOW_SUPERUSER=1 \
    -e COMPOSER_DISABLE_XDEBUG_WARN=1 \
    "${FLOOR_IMAGE}" \
    bash -lc '
set -euo pipefail
source /opt/gate-output.sh
git config --global --add safe.directory /app >/dev/null 2>&1 || true
COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

composer_update_out="$(mktemp)"
set +e
php -d error_reporting=32767 /tmp/composer.phar update --no-interaction --prefer-dist --no-progress --no-ansi >"${composer_update_out}" 2>&1
composer_update_status=$?
set -e
cat "${composer_update_out}"
gate_reject_deprecations "composer update" "${composer_update_out}" || exit 1
if [[ ${composer_update_status} -ne 0 ]]; then
    rm -f "${composer_update_out}"
    exit "${composer_update_status}"
fi
rm -f "${composer_update_out}"
'

docker_cmd run --rm \
    -v "${PHPSTAN_WORK}:/app" \
    -v "${REPO_ROOT}/scripts/gate-output.sh:/opt/gate-output.sh:ro" \
    -w /app \
    -e COMPOSER_ALLOW_SUPERUSER=1 \
    -e COMPOSER_DISABLE_XDEBUG_WARN=1 \
    "${COMPOSER_BIN_IMAGE}" \
    bash -lc '
set -euo pipefail
source /opt/gate-output.sh
apt-get update -qq \
    && apt-get install -y -qq --no-install-recommends curl ca-certificates unzip libzip-dev \
    && docker-php-ext-install -j"$(nproc)" zip >/dev/null
git config --global --add safe.directory /app >/dev/null 2>&1 || true
COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

bin_update_out="$(mktemp)"
set +e
php -d error_reporting=32767 /tmp/composer.phar bin phpstan update --no-interaction --prefer-dist --no-progress --no-ansi --ignore-platform-reqs >"${bin_update_out}" 2>&1
bin_update_status=$?
set -e
cat "${bin_update_out}"
gate_reject_deprecations "composer bin phpstan update" "${bin_update_out}" || exit 1
if [[ ${bin_update_status} -ne 0 ]]; then
    rm -f "${bin_update_out}"
    exit "${bin_update_status}"
fi
rm -f "${bin_update_out}"

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --abandoned=report --no-ansi >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
gate_reject_deprecations "composer audit" "${audit_out}" || exit 1
if [[ ${audit_status} -ne 0 ]]; then
    rm -f "${audit_out}"
    exit "${audit_status}"
fi
gate_require_exact_line "composer audit" "${audit_out}" "^No security vulnerability advisories found\.$" || exit 1
rm -f "${audit_out}"
'

docker_cmd run --rm \
    -v "${PHPSTAN_WORK}:/app:ro" \
    -v "${REPO_ROOT}/scripts/gate-output.sh:/opt/gate-output.sh:ro" \
    -w /app \
    "${PHPSTAN_IMAGE}" \
    bash -lc '
set -euo pipefail
source /opt/gate-output.sh
phpstan_out="$(mktemp)"
set +e
php -d error_reporting=32767 vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G --no-progress --no-ansi >"${phpstan_out}" 2>&1
phpstan_status=$?
set -e
cat "${phpstan_out}"
gate_reject_deprecations "phpstan" "${phpstan_out}" || exit 1
if [[ ${phpstan_status} -ne 0 ]]; then
    rm -f "${phpstan_out}"
    exit "${phpstan_status}"
fi
gate_require_exact_line "phpstan" "${phpstan_out}" "^[[:space:]]*\\[OK\\] No errors[[:space:]]*$" || exit 1
rm -f "${phpstan_out}"
'

echo 'phpstan system-test: passed.'
