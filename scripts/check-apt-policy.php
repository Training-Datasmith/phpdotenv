<?php

declare(strict_types=1);

/**
 * Scan every Dockerfile for insecure apt configuration.
 */

$root = dirname(__DIR__);

/**
 * @return list<string>
 */
function discover_dockerfiles(string $root): array
{
    $dockerfiles = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->isFile() && $file->getFilename() === 'Dockerfile') {
            $path = $file->getPathname();
            if (strpos($path, '/vendor/') !== false) {
                continue;
            }
            $dockerfiles[] = $path;
        }
    }

    return $dockerfiles;
}

/**
 * @param list<string> $lines
 * @return list<string>
 */
function audit_dockerfile_lines(array $lines, string $dockerfile): array
{
    $errors = [];
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\bhttp:\/\//i', $line)) {
            $errors[] = sprintf('disallowed http URL in %s:%d', $dockerfile, $lineNum + 1);
        }
        if (preg_match('/Acquire::Check-Valid-Until\s*"?false"?/i', $line)
            && !preg_match('/Acquire::https::archive\.debian\.org::Check-Valid-Until/', $line)) {
            $errors[] = sprintf('unscoped Check-Valid-Until false in %s:%d', $dockerfile, $lineNum + 1);
        }
        if (preg_match('/--allow-unauthenticated|AllowInsecureRepositories|trusted=yes|gpgv.*--no/i', $line)) {
            $errors[] = sprintf('signature bypass in %s:%d', $dockerfile, $lineNum + 1);
        }
    }

    return $errors;
}

$dockerfiles = discover_dockerfiles($root);
if ($dockerfiles === []) {
    fwrite(STDERR, "check-apt-policy: no Dockerfiles found.\n");
    exit(1);
}

$failed = false;
foreach ($dockerfiles as $dockerfile) {
    $lines = file($dockerfile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        $failed = true;
        fwrite(STDERR, "check-apt-policy: could not read {$dockerfile}.\n");
        continue;
    }
    foreach (audit_dockerfile_lines($lines, $dockerfile) as $error) {
        $failed = true;
        fwrite(STDERR, 'check-apt-policy: ' . $error . "\n");
    }
}

// In-memory demo: http apt URL must be rejected (no vendor/ writes).
$demoLines = [
    'RUN apt-get update -o Acquire::Check-Valid-Until=false',
    'deb http://archive.debian.org/debian buster main',
];
$demoErrors = audit_dockerfile_lines($demoLines, 'in-memory-demo');
if ($demoErrors === []) {
    fwrite(STDERR, "check-apt-policy: in-memory demo failed (http URL was accepted).\n");
    exit(1);
}

$demoGood = audit_dockerfile_lines(
    ['apt-get -o Acquire::https::archive.debian.org::Check-Valid-Until=false update;'],
    'in-memory-demo-good'
);
if ($demoGood !== []) {
    fwrite(STDERR, "check-apt-policy: in-memory demo failed (valid archive line rejected).\n");
    exit(1);
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-apt-policy: ok\n");
exit(0);
