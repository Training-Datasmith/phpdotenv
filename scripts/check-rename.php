<?php

declare(strict_types=1);

/**
 * Enforce snake_case repo-owned names and block stale upstream identifiers.
 */

$root = dirname(__DIR__);

$staleSubstrings = [
    'createImmutable',
    'createMutable',
    'createUnsafeImmutable',
    'createUnsafeMutable',
    'safeLoad',
    'ifPresent',
    'notEmpty',
    'allowedValues',
    'allowedRegexValues',
    'RepositoryBuilder',
    'StoreBuilder',
    'InvalidPathException',
    'InvalidEncodingException',
    'InvalidFileException',
    'ValidationException',
    'ArrayAdapter',
    'EnvConstAdapter',
    'ServerConstAdapter',
    'PutenvAdapter',
    'EntryParser',
    'Php_Option',
    'Graham_Campbell',
    'from_value',
    'from_arrays_value',
    'is_defined',
    'get_or_else',
    'flat_map',
    'map_error',
    'LoaderInterface',
    'ParserInterface',
    'StoreInterface',
    'RepositoryInterface',
    'DotenvTest',
    'RepositoryBuilder',
];

function rel_path(string $path, string $root): string
{
    $prefix = rtrim($root, '/') . '/';
    return strpos($path, $prefix) === 0 ? substr($path, strlen($prefix)) : $path;
}

/**
 * @return list<string>
 */
function collect_php_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Option::fromValue is allowed only inside Str::pos.
 *
 * @return list<string>
 */
function check_str_from_value_usage(string $rel, string $content): array
{
    if ($rel !== 'src/Util/Str.php') {
        return [];
    }
    $errors = [];
    $inPos = false;
    foreach (explode("\n", $content) as $i => $line) {
        $lineNo = $i + 1;
        if (preg_match('/function\s+pos\s*\(/', $line)) {
            $inPos = true;
        } elseif ($inPos && preg_match('/\bfunction\s+\w+\s*\(/', $line) && !preg_match('/function\s+pos\s*\(/', $line)) {
            $inPos = false;
        }
        if (strpos($line, 'fromValue') !== false && !$inPos) {
            $errors[] = "{$rel}:{$lineNo} fromValue outside allowlisted Str::pos site";
        }
    }

    return $errors;
}

function scan_text(string $rel, string $content, array $staleSubstrings): array
{
    $errors = check_str_from_value_usage($rel, $content);
    foreach (explode("\n", $content) as $i => $line) {
        $lineNo = $i + 1;
        foreach ($staleSubstrings as $stale) {
            if (strpos($line, $stale) !== false) {
                $errors[] = "{$rel}:{$lineNo} stale identifier substring: {$stale}";
            }
        }
        if (preg_match('/\b([a-z][a-z0-9]*_[A-Za-z][A-Za-z0-9_]*)\b/', $line, $m)) {
            if (preg_match('/[A-Z]/', explode('_', $m[1], 2)[1] ?? '')) {
                if (strpos($rel, 'tests/') === 0 || strpos($rel, 'src/') === 0 || strpos($rel, 'examples/') === 0) {
                    $errors[] = "{$rel}:{$lineNo} repo-owned identifier not snake_case: {$m[1]}";
                }
            }
        }
        if (preg_match('/\bfinal\s+class\s+([A-Za-z][a-zA-Z0-9_]*)\b/', $line, $classMatch)) {
            $class = $classMatch[1];
            if (preg_match('/[a-z][A-Z]/', $class) && strpos($class, '_') === false) {
                $errors[] = "{$rel}:{$lineNo} test/class name must be Studly_Snake: {$class}";
            }
        }
        if (preg_match('/@param[^\n]*\$allowList\b/', $line)) {
            $errors[] = "{$rel}:{$lineNo} stale doc param name: \$allowList";
        }
    }

    return $errors;
}

function run_allowlisted_upstream_demo(): void
{
    $goodSnippet = <<<'PHP'
final class Str
{
    public static function pos(string $haystack, string $needle)
    {
        return Option::fromValue(1, false);
    }
}
PHP;
    $badSnippet = <<<'PHP'
final class Str
{
    public static function len(string $input): int
    {
        return Option::fromValue(1, false)->get();
    }
}
PHP;
    if (check_str_from_value_usage('src/Util/Str.php', $goodSnippet) !== []) {
        fwrite(STDERR, "check-rename: allowlist demo failed (listed site rejected).\n");
        exit(1);
    }
    if (check_str_from_value_usage('src/Util/Str.php', $badSnippet) === []) {
        fwrite(STDERR, "check-rename: allowlist demo failed (unlisted site accepted).\n");
        exit(1);
    }
}

$errors = [];
foreach (['src', 'tests', 'examples'] as $subdir) {
    foreach (collect_php_files($root . '/' . $subdir) as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        $errors = array_merge($errors, scan_text(rel_path($file, $root), $content, $staleSubstrings));
    }
}

foreach (['README.md', 'UPGRADING.md', 'ARCHITECTURE.md'] as $md) {
    $path = $root . '/' . $md;
    if (!is_readable($path)) {
        continue;
    }
    $content = (string) file_get_contents($path);
    foreach (explode("\n", $content) as $i => $line) {
        if (strpos($line, '`') === false && strpos($line, '::') === false) {
            continue;
        }
        foreach ([
            'createImmutable',
            'createMutable',
            'createUnsafeImmutable',
            'createUnsafeMutable',
            'RepositoryBuilder',
            'StoreBuilder',
            'safeLoad',
            'notEmpty',
            'allowedValues',
            'ifPresent',
            'isInteger',
            'isBoolean',
            'allowList',
        ] as $stale) {
            if (strpos($line, $stale) !== false) {
                $errors[] = "{$md}:" . ($i + 1) . " stale docs reference: {$stale}";
            }
        }
    }
}

run_allowlisted_upstream_demo();

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'check-rename: ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-rename: ok\n");
exit(0);
