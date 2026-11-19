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
    'SUPPORT.md',
    'CHANGELOG.md',
    'docs/public-api.md',
    'docs/plugin-development.md',
    'docs/release-notes/README.md',
    'docs/release-notes/0.1.0-preview.1.md',
    'docs/releasing-framework.md',
    'docs/upgrade-and-recovery.md',
    'docs/administration-sdk.md',
    'docs/http-api.md',
    'docs/github-publication-checklist.md',
    'plugins/README.md',
    'tools/build-release.php',
    'tools/release-note-policy.php',
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

$publicApiDocumentation = (string) file_get_contents($root.'/docs/public-api.md');
foreach ([
    'ColorSchemeInterface',
    'ThemeConfigurationInterface',
    'ThemeDefinition',
    'ThemeRegistryInterface',
] as $themeContract) {
    $require(
        str_contains($publicApiDocumentation, $themeContract),
        sprintf('Public API documentation must list the stable theme contract %s.', $themeContract)
    );
}
$require(
    preg_match('/\| Theme registry and semantic theme package contract \|[^\r\n]+\| stable in API 1\.x \|/', $publicApiDocumentation) === 1,
    'The implemented theme contract must be classified as stable in the extension-point inventory.'
);
$require(
    !preg_match('/Theme registry and semantic theme package contract[^\r\n]+(?:planned|experimental)/i', $publicApiDocumentation),
    'The implemented theme contract must not be classified as planned or experimental.'
);

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
