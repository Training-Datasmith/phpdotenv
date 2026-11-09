#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

git config --global --add safe.directory /app >/dev/null 2>&1 || true

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning or Deprecated lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
set +e
php -d error_reporting=32767 /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
rm -f "${composer_out}"

php -d error_reporting=32767 scripts/check-cluster-pins.php
php -d error_reporting=32767 scripts/check-apt-policy.php

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
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
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi
php -d error_reporting=32767 scripts/check-phpunit-gate.php "${phpunit_out}" tests/baseline-ok.txt scripts/allowed-skipped-tests.txt
rm -f "${phpunit_out}"

echo 'system-test: all steps passed.'
