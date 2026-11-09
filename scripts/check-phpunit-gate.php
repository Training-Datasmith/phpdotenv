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

/**
 * @param list<string> $allowed
 * @return list<string>
 */
function validate_skip_policy(string $stripped, string $baseline, array $allowed): array
{
    $errors = [];

    foreach (explode("\n", $stripped) as $line) {
        if (preg_match('/^(PHP )?(Warning|Deprecated|Notice):/', $line)) {
            $errors[] = 'output contains Warning, Deprecated, or Notice line';
            break;
        }
    }

    $okFound = false;
    foreach (explode("\n", $stripped) as $line) {
        if (preg_match('/^' . preg_quote($baseline, '/') . '\s*$/', $line)) {
            $okFound = true;
            break;
        }
    }
    if (!$okFound) {
        $errors[] = "missing exact baseline success line: {$baseline}";
    }

    foreach (explode("\n", $stripped) as $line) {
        $trim = trim($line);
        if ($trim !== ''
            && preg_match('/ skipped$/', $trim)
            && !preg_match('/::/', $trim)) {
            $errors[] = 'unexpected PHPUnit skip summary in output';
            break;
        }
    }

    if (preg_match_all('/^\s*([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)\s+skipped/m', $stripped, $skipLines)) {
        foreach ($skipLines[1] as $skip) {
            if (!in_array($skip, $allowed, true)) {
                $errors[] = "unlisted skipped test: {$skip}";
            }
        }
    }

    foreach ($allowed as $required) {
        $pattern = '/^\s*' . preg_quote($required, '/') . '\s+skipped/m';
        if (!preg_match($pattern, $stripped)) {
            $errors[] = "missing listed skip: {$required}";
        }
    }

    return $errors;
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

$policyErrors = validate_skip_policy($stripped, $baseline, $allowed);
if ($policyErrors !== []) {
    foreach ($policyErrors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

// In-memory skip-policy demos (no vendor/ writes).
$demoBaseline = 'OK (1 test, 1 assertion)';
$demoOutputUnlisted = "{$demoBaseline}\nDotenv\\Tests\\Demo::test_unlisted skipped\n";
$demoAllowedMissing = "{$demoBaseline}\n";
$demoListedSkip = "{$demoBaseline}\nDotenv\\Tests\\Demo::test_listed skipped\n";
$allowOne = ['Dotenv\\Tests\\Demo::test_listed'];

if (validate_skip_policy($demoOutputUnlisted, $demoBaseline, $allowOne) === []) {
    fwrite(STDERR, "Skip demo failed: unlisted skip must be rejected.\n");
    exit(1);
}
if (validate_skip_policy($demoAllowedMissing, $demoBaseline, $allowOne) === []) {
    fwrite(STDERR, "Skip demo failed: missing listed skip must be rejected.\n");
    exit(1);
}
if (validate_skip_policy($demoListedSkip, $demoBaseline, $allowOne) !== []) {
    fwrite(STDERR, "Skip demo failed: listed skip must be accepted.\n");
    exit(1);
}

fwrite(STDOUT, "phpunit-gate: ok\n");
exit(0);
