<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Plugin\Compatibility\VersionConstraint;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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

        $runtimeManifest = $this->readJson($directory.'/plugin.json', 'plugins.error.manifest_missing', 'plugins.error.manifest_invalid');
        if (!in_array($runtimeManifest['uninstall']['data'] ?? null, ['remove', 'retain'], true)) {
            throw new PluginPackageException('plugins.error.uninstall_policy_invalid');
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
        $this->validateEntityReferences($manifest);
        $this->validatePrivileges($manifest);
        $this->validateAssets($directory, $manifest);
        $this->validateYamlConfiguration($directory, $manifest);
        $this->validatePublicApiUsage($directory);

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
        if (!array_is_list($manifest->settings)) {
            throw new PluginPackageException('plugins.error.settings_invalid');
        }
        $keys = [];
        foreach ($manifest->settings as $field) {
            if (!is_array($field) || !is_string($field['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_.-]*$/', $field['key']) || isset($keys[$field['key']])) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if (!in_array($field['type'] ?? null, ['text', 'number', 'checkbox', 'select'], true) || !is_string($field['label'] ?? null)) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if (isset($field['help']) && !is_string($field['help'])) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if (($field['type'] ?? null) === 'select' && (!is_array($field['options'] ?? null) || $field['options'] === [])) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if ((isset($field['min']) && !is_numeric($field['min'])) || (isset($field['max']) && !is_numeric($field['max'])) || (isset($field['step']) && !is_numeric($field['step']))) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if (isset($field['step']) && (float) $field['step'] <= 0) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if (isset($field['min'], $field['max']) && $field['min'] > $field['max']) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            if (isset($field['maxLength']) && (!is_int($field['maxLength']) || $field['maxLength'] < 1 || $field['maxLength'] > 4000)) {
                throw new PluginPackageException('plugins.error.settings_invalid');
            }
            foreach ($field['options'] ?? [] as $option) {
                if (!is_array($option) || !is_scalar($option['value'] ?? null) || !is_string($option['label'] ?? null)) {
                    throw new PluginPackageException('plugins.error.settings_invalid');
                }
            }
            $keys[$field['key']] = true;
        }

    }

    private function requireCatalog(string $directory, string $locale): void
    {
        $file = $directory.'/translations/messages.'.$locale.'.yaml';
        if (!is_file($file) || filesize($file) === 0) {
            throw new PluginPackageException('plugins.error.translation_missing');
        }
    }

    private function validateEntityReferences(PluginManifest $manifest): void
    {
        if (!array_is_list($manifest->entityReferences)) {
            throw new PluginPackageException('plugins.error.entity_references_invalid');
        }
        $seen = [];
        foreach ($manifest->entityReferences as $owner) {
            if (!is_string($owner) || !preg_match('/^[a-z][a-z0-9-]+$/', $owner) || $owner === $manifest->name || isset($seen[$owner])) {
                throw new PluginPackageException('plugins.error.entity_references_invalid');
            }
            if (!array_key_exists($owner, $manifest->requires)) {
                throw new PluginPackageException('plugins.error.entity_dependency_missing');
            }
            $seen[$owner] = true;
        }
    }

    private function validatePrivileges(PluginManifest $manifest): void
    {
        if (!array_is_list($manifest->privileges)) throw new PluginPackageException('plugins.error.privileges_invalid');
        $keys=[];
        foreach($manifest->privileges as $privilege){
            if(!is_array($privilege)||!is_string($privilege['key']??null)||!preg_match('/^'.preg_quote($manifest->name,'/').'\.[a-z][a-z0-9._-]*$/',$privilege['key'])||isset($keys[$privilege['key']])||!is_string($privilege['label']??null)||!is_string($privilege['group']??null))throw new PluginPackageException('plugins.error.privileges_invalid');
            $keys[$privilege['key']]=true;
        }
        foreach($manifest->navigation as $item){$required=$item['privilege']??null;if($required!==null&&(!is_string($required)||!isset($keys[$required])))throw new PluginPackageException('plugins.error.navigation_privilege_invalid');}
    }

    private function validateAssets(string $directory, PluginManifest $manifest): void
    {
        $handles = [];
        foreach (['styles', 'scripts'] as $kind) {
            $assets = $manifest->assets[$kind] ?? [];
            if (!is_array($assets) || !array_is_list($assets)) {
                throw new PluginPackageException('plugins.error.assets_invalid');
            }
            foreach ($assets as $asset) {
                if (!is_array($asset)) {
                    throw new PluginPackageException('plugins.error.assets_invalid');
                }
                $handle = $asset['handle'] ?? null;
                $path = $asset['path'] ?? null;
                if (!is_string($handle) || !preg_match('/^[a-z][a-z0-9._-]*$/', $handle) || isset($handles[$handle])) {
                    throw new PluginPackageException('plugins.error.assets_invalid');
                }
                if (!is_string($path) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\') || !is_file($directory.'/public/'.$path)) {
                    throw new PluginPackageException('plugins.error.asset_missing');
                }
                if (isset($asset['priority']) && !is_int($asset['priority'])) {
                    throw new PluginPackageException('plugins.error.assets_invalid');
                }
                if (isset($asset['replaces']) && (!is_array($asset['replaces']) || array_filter($asset['replaces'], static fn (mixed $value): bool => !is_string($value)))) {
                    throw new PluginPackageException('plugins.error.assets_invalid');
                }
                $handles[$handle] = true;
            }
        }
    }

    private function validateYamlConfiguration(string $directory, PluginManifest $manifest): void
    {
        foreach (['services', 'routes'] as $name) {
            $file = $directory.'/config/'.$name.'.yaml';
            if (!is_file($file)) continue;
            try {
                $config = Yaml::parseFile($file);
            } catch (ParseException) {
                throw new PluginPackageException('plugins.error.'.$name.'_invalid');
            }
            if (!is_array($config) || $config === []) {
                throw new PluginPackageException('plugins.error.'.$name.'_invalid');
            }
            if ($name === 'routes') {
                $guarded = false;
                foreach ($config as $route) {
                    if (!is_array($route)) continue;
                    $defaults = $route['defaults'] ?? [];
                    if (is_array($defaults) && isset($defaults['_plugin']) && $defaults['_plugin'] !== $manifest->name) {
                        throw new PluginPackageException('plugins.error.routes_plugin_mismatch');
                    }
                    if (is_array($defaults) && ($defaults['_plugin'] ?? null) === $manifest->name) $guarded = true;
                }
                if ($manifest->navigation !== [] && !$guarded) throw new PluginPackageException('plugins.error.routes_plugin_guard_missing');
            }
        }
    }

    private function validatePublicApiUsage(string $directory): void
    {
        $source = $directory.'/src';
        if (!is_dir($source)) return;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $contents = (string) file_get_contents($file->getPathname());
            if (str_contains($contents, 'use App\\') || str_contains($contents, '\\App\\')) {
                throw new PluginPackageException('plugins.error.internal_api');
            }
            if (str_contains($contents, 'use Shopware\\') || str_contains($contents, '\\Shopware\\')) {
                throw new PluginPackageException('plugins.error.shopware_api_unported');
            }
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
