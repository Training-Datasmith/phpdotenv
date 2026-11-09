#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_DISABLE_XDEBUG_WARN=1

# shellcheck source=gate-output.sh
source /app/scripts/gate-output.sh

git config --global --add safe.directory /app >/dev/null 2>&1 || true

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
set +e
php -d error_reporting=32767 /tmp/composer.phar update --no-interaction --prefer-dist --no-progress --no-ansi >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
gate_reject_deprecations "composer update" "${composer_out}" || exit 1
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
rm -f "${composer_out}"

php -d error_reporting=32767 scripts/check-cluster-pins.php
php -d error_reporting=32767 scripts/check-apt-policy.php

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

php -d error_reporting=32767 scripts/check-rename.php

lint_out="$(mktemp)"
lint_status=0
while IFS= read -r -d '' file; do
    set +e
    line="$(php -d error_reporting=32767 -l "${file}" 2>&1)"
    set -e
    echo "${line}" >>"${lint_out}"
    if ! grep -E '^No syntax errors detected in '"${file}"'$' <<<"${line}"; then
        lint_status=1
    fi
done < <(find src -name '*.php' -print0 | sort -z)
cat "${lint_out}"
gate_reject_deprecations "php -l" "${lint_out}" || exit 1
if [[ ${lint_status} -ne 0 ]]; then
    exit "${lint_status}"
fi
rm -f "${lint_out}"

phpunit_out="$(mktemp)"
set +e
php -d error_reporting=32767 vendor/bin/phpunit --colors=never --no-coverage >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
gate_reject_deprecations "phpunit" "${phpunit_out}" || exit 1
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi
php -d error_reporting=32767 scripts/check-phpunit-gate.php "${phpunit_out}" tests/baseline-ok.txt scripts/allowed-skipped-tests.txt
rm -f "${phpunit_out}"

echo 'system-test: all steps passed.'
