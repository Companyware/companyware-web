<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Plugin\Compatibility\VersionConstraint;

final class PluginPackageValidator
{
    public function __construct(private readonly VersionConstraint $versions)
    {
    }

    public function validate(string $directory): PluginManifest
    {
        $manifest = PluginManifest::fromFile($directory.'/plugin.json');
        if (!$manifest) {
            throw new PluginPackageException('plugins.error.manifest_invalid');
        }

        $composer = $this->readJson($directory.'/composer.json', 'plugins.error.composer_missing', 'plugins.error.composer_invalid');
        if (($composer['type'] ?? null) !== 'companyware-plugin') {
            throw new PluginPackageException('plugins.error.composer_type');
        }
        if (!is_string($composer['name'] ?? null) || !preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#', $composer['name'])) {
            throw new PluginPackageException('plugins.error.composer_name');
        }
        if (($composer['version'] ?? $manifest->version) !== $manifest->version) {
            throw new PluginPackageException('plugins.error.version_mismatch');
        }

        $extra = $composer['extra']['companyware-plugin'] ?? null;
        if (!is_array($extra) || ($extra['manifest'] ?? null) !== 'plugin.json') {
            throw new PluginPackageException('plugins.error.composer_metadata');
        }

        $this->requireCatalog($directory, 'de');
        if (($extra['official'] ?? false) === true) {
            $this->requireCatalog($directory, 'en');
        }
        foreach ($manifest->locales as $locale) {
            $code = is_array($locale) ? ($locale['code'] ?? null) : null;
            if (!is_string($code) || !preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
                throw new PluginPackageException('plugins.error.locale_invalid');
            }
            $this->requireCatalog($directory, $code);
        }

        $this->validateCompatibility($manifest);

        return $manifest;
    }

    public function validateCompatibility(PluginManifest $manifest): void
    {
        foreach ([$manifest->companyware, $manifest->php, ...array_values($manifest->extensions), ...array_values($manifest->requires), ...array_values($manifest->optional), ...array_values($manifest->conflicts)] as $constraint) {
            if (!is_string($constraint) || !$this->versions->isValid($constraint)) {
                throw new PluginPackageException('plugins.error.constraint_invalid');
            }
        }
        foreach ([$manifest->extensions, $manifest->requires, $manifest->optional, $manifest->conflicts, $manifest->provides, $manifest->replaces] as $map) {
            foreach ($map as $name => $value) {
                if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_.-]*$/', $name) || !is_string($value)) {
                    throw new PluginPackageException('plugins.error.compatibility_invalid');
                }
            }
        }
        foreach ([$manifest->provides, $manifest->replaces] as $map) {
            foreach ($map as $version) {
                if (!$this->versions->isVersion($version)) {
                    throw new PluginPackageException('plugins.error.provided_version_invalid');
                }
            }
        }

    }

    private function requireCatalog(string $directory, string $locale): void
    {
        $file = $directory.'/translations/messages.'.$locale.'.yaml';
        if (!is_file($file) || filesize($file) === 0) {
            throw new PluginPackageException('plugins.error.translation_missing');
        }
    }

    private function readJson(string $file, string $missingError, string $invalidError): array
    {
        if (!is_file($file)) {
            throw new PluginPackageException($missingError);
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PluginPackageException($invalidError);
        }
        if (!is_array($data)) {
            throw new PluginPackageException($invalidError);
        }

        return $data;
    }
}
