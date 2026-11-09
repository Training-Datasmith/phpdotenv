#!/usr/bin/env bash
# shellcheck disable=SC2034
# Shared helpers for strict full-line gates and deprecation rejection.
set -euo pipefail

gate_strip_ansi() {
    local file="$1"
    local dest="$2"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${dest}"
}

gate_reject_deprecations() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    gate_strip_ansi "${file}" "${stripped}"
    if grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        return 1
    fi
    if grep -E '^(Deprecation Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Deprecation Notice lines" >&2
        grep -E '^(Deprecation Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        return 1
    fi
    rm -f "${stripped}"
    return 0
}

gate_require_exact_line() {
    local label="$1"
    local file="$2"
    local pattern="$3"
    local stripped
    stripped="$(mktemp)"
    gate_strip_ansi "${file}" "${stripped}"
    if ! grep -E "${pattern}" "${stripped}" >/dev/null; then
        echo "${label}: missing required anchored line matching ${pattern}" >&2
        rm -f "${stripped}"
        return 1
    fi
    rm -f "${stripped}"
    return 0
}
