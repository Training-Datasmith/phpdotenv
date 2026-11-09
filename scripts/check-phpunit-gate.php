<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/check-phpunit-gate.php <output.log> <baseline-ok.txt> [allowed-skips.txt]\n");
    exit(1);
}

$logPath = $argv[1];
$baselinePath = $argv[2];
$allowedSkipsPath = $argv[3] ?? __DIR__ . '/allowed-skipped-tests.txt';

if (!is_readable($logPath)) {
    fwrite(STDERR, "Missing log file: {$logPath}\n");
    exit(1);
}
if (!is_readable($baselinePath)) {
    fwrite(STDERR, "Missing baseline file: {$baselinePath}\n");
    exit(1);
}

$baseline = trim((string) file_get_contents($baselinePath));
if ($baseline === '') {
    fwrite(STDERR, "Baseline file is empty.\n");
    exit(1);
}

$raw = (string) file_get_contents($logPath);
$stripped = preg_replace('/\x1b\[[0-9;]*[[:alpha:]]/', '', $raw) ?? $raw;

if (preg_match('/(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):/m', $stripped)) {
    fwrite(STDERR, "PHPUnit output contains Warning, Deprecated, or Notice.\n");
    exit(1);
}

$okFound = false;
foreach (explode("\n", $stripped) as $line) {
    $trim = trim($line);
    if ($trim === $baseline) {
        $okFound = true;
        break;
    }
}

if (!$okFound) {
    fwrite(STDERR, "PHPUnit missing exact baseline success line: {$baseline}\n");
    exit(1);
}

/** @var list<string> $allowed */
$allowed = [];
if (is_readable($allowedSkipsPath)) {
    foreach (file($allowedSkipsPath, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line !== '' && strpos($line, '#') !== 0) {
            $allowed[] = $line;
        }
    }
}

if (preg_match_all('/^.* skipped$/m', $stripped, $skipSummary) && $skipSummary[0] !== []) {
    fwrite(STDERR, "Unexpected PHPUnit skip summary in output.\n");
    exit(1);
}

if (preg_match_all('/^\s*([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)\s+skipped/m', $stripped, $skipLines)) {
    foreach ($skipLines[1] as $skip) {
        if (!in_array($skip, $allowed, true)) {
            fwrite(STDERR, "Unlisted skipped test: {$skip}\n");
            exit(1);
        }
    }
}

// In-memory skip-policy demos (no vendor/ writes).
$demoOutputUnlisted = "OK (1 test, 1 assertion)\nDotenv\\Tests\\Demo::test_unlisted skipped\n";
$demoAllowedMissing = "OK (1 test, 1 assertion)\n";
$allowedDemo = ['Dotenv\\Tests\\Demo::test_listed'];

function demo_skip_gate(string $output, string $baseline, array $allowed): array
{
    $errors = [];
    if (preg_match_all('/^\s*([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)\s+skipped/m', $output, $skipLines)) {
        foreach ($skipLines[1] as $skip) {
            if (!in_array($skip, $allowed, true)) {
                $errors[] = $skip;
            }
        }
    }

    return $errors;
}

if (demo_skip_gate($demoOutputUnlisted, $baseline, $allowedDemo) === []) {
    fwrite(STDERR, "Skip demo failed: unlisted skip must be rejected.\n");
    exit(1);
}
if (demo_skip_gate($demoAllowedMissing, $baseline, ['Dotenv\\Tests\\Demo::test_listed']) === []) {
    // missing listed skip is fine; demo checks that absent skip does not false-positive as unlisted
} else {
    fwrite(STDERR, "Skip demo failed: missing listed skip treated as error incorrectly.\n");
    exit(1);
}

fwrite(STDOUT, "phpunit-gate: ok\n");
exit(0);
