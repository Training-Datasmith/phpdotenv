<?php

declare(strict_types=1);

/**
 * Verify cluster polyfills match the composer-index SHAs and root aliases.
 * Includes in-memory wrong-SHA demos for installed.php and installed.json.
 */

$expected = [
    'symfony/polyfill-ctype' => [
        'alias' => '1.33.0',
        'reference' => '178d8180c34087830dbcdb5e67fa8b61d28016ac',
        'version' => 'dev-remote-test-2026-10-07',
        'source_url' => 'https://github.com/Training-Datasmith/polyfill-ctype.git',
    ],
    'symfony/polyfill-mbstring' => [
        'alias' => '1.33.0',
        'reference' => '2cecae254e4ffc2c20ac379c5cd23d91c82109df',
        'version' => 'dev-remote-test-2026-10-07',
        'source_url' => 'https://github.com/Training-Datasmith/polyfill-mbstring.git',
    ],
];

$packagistOnly = [
    'phpunit/phpunit' => [
        'min_version' => '8.5.52',
        'forbidden_urls' => [
            'Training-Datasmith/phpunit',
            'Training-Datasmith',
        ],
        'forbidden_references' => [
            '178d8180c34087830dbcdb5e67fa8b61d28016ac',
            '2cecae254e4ffc2c20ac379c5cd23d91c82109df',
            '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
            'a37d66fabf01af0eedabffe271b70dbe71723001',
        ],
    ],
];

function fail_checks(string $message): void
{
    fwrite(STDERR, 'check-cluster-pins: ' . $message . "\n");
    exit(1);
}

/**
 * @return array<string, mixed>
 */
function decode_json(string $json): array
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fail_checks('invalid JSON payload: ' . json_last_error_msg());
    }

    return $data;
}

/**
 * @param array<string, array{alias: string, reference: string, version: string, source_url: string}> $expected
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versions
 */
function verify_installed_php(array $expected, array $versions): array
{
    $errors = [];
    foreach ($expected as $name => $want) {
        if (!isset($versions[$name])) {
            $errors[] = sprintf('%s is not installed (installed.php).', $name);
            continue;
        }
        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            $errors[] = sprintf(
                '%s alias mismatch (installed.php): expected %s in %s.',
                $name,
                $want['alias'],
                json_encode($aliases)
            );
        }
        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            $errors[] = sprintf('%s has no reference (installed.php).', $name);
            continue;
        }
        if (!hash_equals($want['reference'], $reference)) {
            $errors[] = sprintf(
                '%s reference mismatch (installed.php): expected %s, got %s.',
                $name,
                $want['reference'],
                $reference
            );
        }
    }

    return $errors;
}

/**
 * @param array<string, array{alias: string, reference: string, version: string, source_url: string}> $expected
 */
function verify_installed_json(array $expected, string $installedJsonPath): array
{
    if (!is_readable($installedJsonPath)) {
        return [sprintf('Missing %s; run composer update first.', $installedJsonPath)];
    }
    $raw = file_get_contents($installedJsonPath);
    if ($raw === false) {
        return [sprintf('Could not read %s.', $installedJsonPath)];
    }
    /** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $data */
    $data = decode_json($raw);
    $packages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);

    /** @var array<string, array<string, mixed>> $byName */
    $byName = [];
    foreach ($packages as $package) {
        if (!isset($package['name'], $package['version'])) {
            continue;
        }
        $byName[(string) $package['name']] = $package;
    }

    $errors = [];
    foreach ($expected as $name => $want) {
        if (!isset($byName[$name])) {
            $errors[] = sprintf('%s is not installed (installed.json).', $name);
            continue;
        }
        $pkg = $byName[$name];
        if (($pkg['version'] ?? '') !== $want['version']) {
            $errors[] = sprintf(
                '%s version mismatch (installed.json): expected %s, got %s.',
                $name,
                $want['version'],
                (string) ($pkg['version'] ?? '')
            );
        }
        $reference = $pkg['source']['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            $errors[] = sprintf('%s has no source.reference (installed.json).', $name);
            continue;
        }
        if (!hash_equals($want['reference'], $reference)) {
            $errors[] = sprintf(
                '%s source.reference mismatch (installed.json): expected %s, got %s.',
                $name,
                $want['reference'],
                $reference
            );
        }
        $sourceUrl = (string) ($pkg['source']['url'] ?? '');
        if ($sourceUrl !== $want['source_url']) {
            $errors[] = sprintf(
                '%s source.url mismatch (installed.json): expected %s, got %s.',
                $name,
                $want['source_url'],
                $sourceUrl
            );
        }
    }

    return $errors;
}

/**
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versions
 */
function verify_packagist_phpunit_installed_php(array $packagistOnly, array $versions): array
{
    $errors = [];
    if (!isset($versions['phpunit/phpunit'])) {
        $errors[] = 'phpunit/phpunit is not installed (installed.php).';

        return $errors;
    }
    $pkg = $versions['phpunit/phpunit'];
    $policy = $packagistOnly['phpunit/phpunit'];
    $pretty = (string) ($pkg['pretty_version'] ?? ($pkg['version'] ?? ''));
    if ($pretty !== '' && version_compare($pretty, $policy['min_version'], '<')) {
        $errors[] = sprintf(
            'phpunit/phpunit version %s is below minimum %s (installed.php).',
            $pretty,
            $policy['min_version']
        );
    }
    $reference = strtolower((string) ($pkg['reference'] ?? ''));
    foreach ($policy['forbidden_references'] as $forbiddenRef) {
        if ($reference === strtolower($forbiddenRef)) {
            $errors[] = 'phpunit/phpunit must not resolve from the composer-index (installed.php reference).';
        }
    }
    $serialized = strtolower(json_encode($pkg) ?: '');
    foreach ($policy['forbidden_urls'] as $needle) {
        if (strpos($serialized, strtolower($needle)) !== false) {
            $errors[] = 'phpunit/phpunit must not be installed from Training-Datasmith (installed.php).';
        }
    }

    return $errors;
}

/**
 * @param array<string, array{min_version: string, forbidden_urls: list<string>}> $packagistOnly
 */
function verify_packagist_phpunit_installed_json(array $packagistOnly, string $installedJsonPath): array
{
    $raw = file_get_contents($installedJsonPath);
    if ($raw === false) {
        return ['Could not read installed.json for phpunit gap check.'];
    }
    $data = decode_json($raw);
    $packages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);
    $errors = [];
    foreach ($packages as $package) {
        if (($package['name'] ?? '') !== 'phpunit/phpunit') {
            continue;
        }
        $version = (string) ($package['version'] ?? '');
        if (version_compare($version, $packagistOnly['phpunit/phpunit']['min_version'], '<')) {
            $errors[] = sprintf('phpunit/phpunit version %s is below minimum %s.', $version, $packagistOnly['phpunit/phpunit']['min_version']);
        }
        $sourceUrl = (string) ($package['source']['url'] ?? '');
        $distUrl = (string) ($package['dist']['url'] ?? '');
        foreach ($packagistOnly['phpunit/phpunit']['forbidden_urls'] as $needle) {
            if (stripos($sourceUrl, $needle) !== false || stripos($distUrl, $needle) !== false) {
                $errors[] = 'phpunit/phpunit must not be installed from Training-Datasmith (installed.json URLs).';
            }
        }
        $reference = strtolower((string) ($package['source']['reference'] ?? ''));
        foreach ($packagistOnly['phpunit/phpunit']['forbidden_references'] as $forbiddenRef) {
            if ($reference === strtolower($forbiddenRef)) {
                $errors[] = 'phpunit/phpunit must not use a forbidden index SHA (installed.json).';
            }
        }
    }

    return $errors;
}

function run_wrong_sha_demos(array $expected): void
{
    $badReference = '0000000000000000000000000000000000000000';

    $goodVersions = [
        'symfony/polyfill-ctype' => [
            'reference' => $expected['symfony/polyfill-ctype']['reference'],
            'aliases' => [$expected['symfony/polyfill-ctype']['alias']],
        ],
        'symfony/polyfill-mbstring' => [
            'reference' => $expected['symfony/polyfill-mbstring']['reference'],
            'aliases' => [$expected['symfony/polyfill-mbstring']['alias']],
        ],
    ];

    $goodJson = [
        'packages' => [
            [
                'name' => 'symfony/polyfill-ctype',
                'version' => $expected['symfony/polyfill-ctype']['version'],
                'source' => [
                    'type' => 'git',
                    'url' => $expected['symfony/polyfill-ctype']['source_url'],
                    'reference' => $expected['symfony/polyfill-ctype']['reference'],
                ],
            ],
            [
                'name' => 'symfony/polyfill-mbstring',
                'version' => $expected['symfony/polyfill-mbstring']['version'],
                'source' => [
                    'type' => 'git',
                    'url' => $expected['symfony/polyfill-mbstring']['source_url'],
                    'reference' => $expected['symfony/polyfill-mbstring']['reference'],
                ],
            ],
        ],
        'packages-dev' => [],
    ];
    $tmpGood = tempnam(sys_get_temp_dir(), 'installed-good-');
    $tmpBad = tempnam(sys_get_temp_dir(), 'installed-bad-');
    if ($tmpGood === false || $tmpBad === false) {
        fail_checks('could not create temp files for wrong-SHA demos.');
    }
    file_put_contents($tmpGood, json_encode($goodJson));
    $badJson = $goodJson;
    $badJson['packages'][0]['source']['reference'] = $badReference;
    file_put_contents($tmpBad, json_encode($badJson));

    $badPhpVersions = $goodVersions;
    $badPhpVersions['symfony/polyfill-ctype']['reference'] = $badReference;
    $phpErrors = verify_installed_php($expected, $badPhpVersions);
    if ($phpErrors === []) {
        @unlink($tmpGood);
        @unlink($tmpBad);
        fail_checks('wrong-SHA demo (installed.php) did not fail.');
    }
    $phpOk = verify_installed_php($expected, $goodVersions);
    if ($phpOk !== []) {
        @unlink($tmpGood);
        @unlink($tmpBad);
        fail_checks('wrong-SHA demo (installed.php) broke good map: ' . implode(' ', $phpOk));
    }
    $jsonCrossAfterPhp = verify_installed_json($expected, $tmpGood);
    if ($jsonCrossAfterPhp !== []) {
        @unlink($tmpGood);
        @unlink($tmpBad);
        fail_checks('wrong-SHA demo (installed.php): installed.json good map must still pass: ' . implode(' ', $jsonCrossAfterPhp));
    }

    $jsonErrors = verify_installed_json($expected, $tmpBad);
    if ($jsonErrors === []) {
        @unlink($tmpGood);
        @unlink($tmpBad);
        fail_checks('wrong-SHA demo (installed.json) did not fail.');
    }
    $jsonOk = verify_installed_json($expected, $tmpGood);
    if ($jsonOk !== []) {
        @unlink($tmpGood);
        @unlink($tmpBad);
        fail_checks('wrong-SHA demo (installed.json) broke good map: ' . implode(' ', $jsonOk));
    }
    $phpCrossAfterJson = verify_installed_php($expected, $goodVersions);
    if ($phpCrossAfterJson !== []) {
        @unlink($tmpGood);
        @unlink($tmpBad);
        fail_checks('wrong-SHA demo (installed.json): installed.php good map must still pass: ' . implode(' ', $phpCrossAfterJson));
    }
    @unlink($tmpGood);
    @unlink($tmpBad);
}

$installedPhp = __DIR__ . '/../vendor/composer/installed.php';
$installedJson = __DIR__ . '/../vendor/composer/installed.json';
if (!is_readable($installedPhp)) {
    fail_checks('vendor/composer/installed.php not found; run composer update first.');
}
if (!is_readable($installedJson)) {
    fail_checks('vendor/composer/installed.json not found; run composer update first.');
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installed */
$installed = require $installedPhp;
$versions = $installed['versions'] ?? [];

$errors = array_merge(
    verify_installed_php($expected, $versions),
    verify_installed_json($expected, $installedJson),
    verify_packagist_phpunit_installed_php($packagistOnly, $versions),
    verify_packagist_phpunit_installed_json($packagistOnly, $installedJson)
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'check-cluster-pins: ' . $error . "\n");
    }
    exit(1);
}

run_wrong_sha_demos($expected);

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
