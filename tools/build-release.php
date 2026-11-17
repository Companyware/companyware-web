<?php
/*
 * Companyware Web reproducible release archive builder
 * Copyright (c) 2026 Companyware contributors
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */

declare(strict_types=1);

require __DIR__.'/release-note-policy.php';

$root = dirname(__DIR__);
$arguments = array_values(array_slice($argv, 1));
$verifyReproducible = false;

if (($position = array_search('--verify-reproducible', $arguments, true)) !== false) {
    $verifyReproducible = true;
    unset($arguments[$position]);
    $arguments = array_values($arguments);
}

$version = $arguments[0] ?? '';
$outputDirectory = $arguments[1] ?? $root.'/dist/releases';

if (count($arguments) > 2 || preg_match('/^(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)(?:-[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?(?:\\+[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?$/', $version) !== 1) {
    fwrite(STDERR, "Usage: php tools/build-release.php <semver> [output-directory] [--verify-reproducible]\n");
    exit(2);
}

$run = static function (array $command) use ($root): string {
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException(sprintf('Unable to start command: %s', implode(' ', $command)));
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException(trim($stderr) ?: sprintf('Command failed with exit code %d.', $exitCode));
    }

    return trim((string) $stdout);
};

try {
    if ($run(['git', 'status', '--porcelain', '--untracked-files=normal']) !== '') {
        throw new RuntimeException('Refusing to build a release archive from a dirty working tree.');
    }

    $releaseNotesRelativePath = sprintf('docs/release-notes/%s.md', $version);
    $releaseNotesPath = $root.'/'.$releaseNotesRelativePath;
    if (!is_file($releaseNotesPath)) {
        throw new RuntimeException(sprintf(
            'Version-bound release notes are required before building an archive: %s',
            $releaseNotesRelativePath
        ));
    }
    $releaseNotes = (string) file_get_contents($releaseNotesPath);
    if (!companywareReleaseNotesHaveVersionHeading($releaseNotes, $version)) {
        throw new RuntimeException(sprintf(
            'The first release-notes line must be exactly "# Companyware Web %s".',
            $version
        ));
    }
    $releaseNotesChecksum = hash_file('sha256', $releaseNotesPath);
    if (!is_string($releaseNotesChecksum)) {
        throw new RuntimeException('Unable to calculate the release-notes checksum.');
    }

    $commit = $run(['git', 'rev-parse', 'HEAD']);
    $commitDate = $run(['git', 'show', '-s', '--format=%cI', 'HEAD']);
    $prefix = sprintf('companyware-web-%s/', $version);

    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException(sprintf('Unable to create output directory: %s', $outputDirectory));
    }

    $outputDirectory = (string) realpath($outputDirectory);
    $archiveName = sprintf('companyware-web-%s.zip', $version);
    $archivePath = $outputDirectory.DIRECTORY_SEPARATOR.$archiveName;
    $temporaryArchive = $outputDirectory.DIRECTORY_SEPARATOR.'.'.$archiveName.'.verify';

    $run(['git', 'archive', '--format=zip', '--prefix='.$prefix, '--output='.$archivePath, $commit]);
    $checksum = hash_file('sha256', $archivePath);
    if (!is_string($checksum)) {
        throw new RuntimeException('Unable to calculate the archive checksum.');
    }

    if ($verifyReproducible) {
        $run(['git', 'archive', '--format=zip', '--prefix='.$prefix, '--output='.$temporaryArchive, $commit]);
        $verificationChecksum = hash_file('sha256', $temporaryArchive);
        @unlink($temporaryArchive);
        if ($verificationChecksum !== $checksum) {
            throw new RuntimeException('Repeated archive creation produced a different SHA-256 checksum.');
        }
    }

    file_put_contents($archivePath.'.sha256', sprintf("%s  %s\n", $checksum, $archiveName));
    $manifest = [
        'format' => 'companyware-release-evidence-1',
        'application' => 'companyware-web',
        'version' => $version,
        'commit' => $commit,
        'commitDate' => $commitDate,
        'publicApi' => '1.0',
        'releaseNotes' => $releaseNotesRelativePath,
        'releaseNotesSha256' => $releaseNotesChecksum,
        'artifact' => $archiveName,
        'bytes' => filesize($archivePath),
        'sha256' => $checksum,
        'reproducibilityVerified' => $verifyReproducible,
    ];
    file_put_contents(
        $archivePath.'.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
    );

    echo sprintf("Built %s\nSHA-256 %s\nCommit %s\n", $archivePath, $checksum, $commit);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] '.$exception->getMessage()."\n");
    exit(1);
}

