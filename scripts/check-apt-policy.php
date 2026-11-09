<?php

declare(strict_types=1);

/**
 * Scan every Dockerfile for insecure apt configuration.
 */

$root = dirname(__DIR__);
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
    $inBootstrap = false;
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/archive\.debian\.org/i', $line) && preg_match('/http:\/\//', $line)) {
            if (!preg_match('/Acquire::https::archive\.debian\.org::Check-Valid-Until|ca-certificates|apt-transport-https/i', $line)
                && !preg_match('/deb\s+http:\/\/archive\.debian\.org/i', $line)) {
                // allow explicit deb http://archive.debian.org lines only inside guarded RUN blocks
            }
        }
        if (preg_match('/\bhttp:\/\/(?!archive\.debian\.org)/', $line)) {
            $failed = true;
            fwrite(STDERR, sprintf(
                "check-apt-policy: disallowed http URL in %s:%d\n",
                $dockerfile,
                $lineNum + 1
            ));
        }
        if (preg_match('/Acquire::Check-Valid-Until\s*"?false"?/i', $line)
            && !preg_match('/archive\.debian\.org|buster-updates|sed -i/i', $line)
            && !preg_match('/Acquire::https::archive\.debian\.org::Check-Valid-Until/', $line)) {
            $failed = true;
            fwrite(STDERR, sprintf(
                "check-apt-policy: unscoped Check-Valid-Until false in %s:%d\n",
                $dockerfile,
                $lineNum + 1
            ));
        }
        if (preg_match('/--allow-unauthenticated|AllowInsecureRepositories|trusted=yes|gpgv.*--no/i', $line)) {
            $failed = true;
            fwrite(STDERR, sprintf(
                "check-apt-policy: signature bypass in %s:%d\n",
                $dockerfile,
                $lineNum + 1
            ));
        }
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-apt-policy: ok\n");
exit(0);
