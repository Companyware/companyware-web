<?php
/*
 * Companyware Web framework release gate
 * Copyright (c) 2026 Companyware contributors
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$require = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$require(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80400, sprintf('PHP 8.3.x is required; running %s.', PHP_VERSION));

$composerFile = $root.'/composer.json';
$composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
$require(($composer['license'] ?? null) === 'MIT', 'composer.json must declare the MIT license.');
$require(($composer['extra']['companyware']['public-api'] ?? null) === '1.0', 'composer.json must declare Companyware public API 1.0.');
$require(is_file($root.'/composer.lock'), 'composer.lock is required for a reproducible framework release.');
$require(preg_match('/^MIT License\R/', (string) file_get_contents($root.'/LICENSE')) === 1, 'LICENSE must contain the MIT license text.');

foreach ([
    'README.md',
    'SECURITY.md',
    'CONTRIBUTING.md',
    'CHANGELOG.md',
    'docs/public-api.md',
    'docs/plugin-development.md',
    'docs/releasing-framework.md',
    'docs/upgrade-and-recovery.md',
    'docs/administration-sdk.md',
    'docs/http-api.md',
    'plugins/README.md',
    'tools/build-release.php',
    '.github/workflows/release-gate.yml',
] as $requiredDocumentation) {
    $require(is_file($root.'/'.$requiredDocumentation), sprintf('Required release documentation is missing: %s.', $requiredDocumentation));
}

$readme = (string) file_get_contents($root.'/README.md');
$require(str_contains($readme, 'Companyware Desktop') && str_contains($readme, 'Companyware Web'), 'README must distinguish the Desktop and Web applications.');
$require(str_contains($readme, 'versioned Companyware HTTP API'), 'README must describe the versioned API boundary between Desktop and Web.');
$require(!preg_match('/default.{0,24}(password|credential|login)/i', $readme), 'README must not advertise default login credentials.');

$publicApiSource = (string) file_get_contents($root.'/src/Core/Framework/Api/PublicApi.php');
$require(str_contains($publicApiSource, "public const VERSION = '1.0';"), 'The PHP public API version must match composer.json.');

foreach (['admin-sdk/dist/companyware-admin-sdk.js', 'admin-sdk/dist/companyware-admin-sdk.d.ts', 'admin-sdk/dist/companyware-admin-tokens.css'] as $artifact) {
    $require(is_file($root.'/'.$artifact) && filesize($root.'/'.$artifact) > 0, sprintf('Required Administration SDK artifact is missing or empty: %s.', $artifact));
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] $failure\n");
    }
    exit(1);
}

echo "Companyware Web framework release prerequisites passed.\n";
