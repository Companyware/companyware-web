<?php

namespace App\Plugin;

final readonly class PluginManifest
{
    public function __construct(public string $name, public string $label, public string $version, public string $description, public string $path, public array $navigation = [], public array $migrations = [], public array $assets = [], public array $locales = [], public bool $core = false, public string $companyware = '*', public string $php = '*', public array $extensions = [], public array $requires = [], public array $optional = [], public array $conflicts = [], public array $provides = [], public array $replaces = [], public array $settings = [], public string $uninstallData = 'remove', public array $entityReferences = [], public array $privileges = [])
    {
    }

    public static function fromFile(string $file): ?self
    {
        if (!is_file($file)) return null;
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? self::fromArray($data, dirname($file)) : null;
    }

    public static function fromArray(array $data, string $path): ?self
    {
        if (!is_array($data) || !preg_match('/^[a-z][a-z0-9-]+$/', $data['name'] ?? '')) return null;
        if (!is_string($data['label'] ?? null) || !preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $data['version'] ?? '')) return null;
        foreach (['require', 'optional', 'conflict', 'provide', 'replace', 'settings'] as $field) {
            if (isset($data[$field]) && !is_array($data[$field])) return null;
        }
        if (isset($data['compatibility']) && !is_array($data['compatibility'])) return null;
        if (isset($data['uninstall']) && !is_array($data['uninstall'])) return null;
        if (isset($data['entities']) && !is_array($data['entities'])) return null;
        if (isset($data['entities']['references']) && !is_array($data['entities']['references'])) return null;
        if (isset($data['privileges']) && !is_array($data['privileges'])) return null;
        $uninstallData = $data['uninstall']['data'] ?? 'remove';
        if (!is_string($uninstallData) || !in_array($uninstallData, ['remove', 'retain'], true)) return null;
        $compatibility = is_array($data['compatibility'] ?? null) ? $data['compatibility'] : [];
        if (isset($compatibility['companyware']) && !is_string($compatibility['companyware'])) return null;
        if (isset($compatibility['php']) && !is_string($compatibility['php'])) return null;
        if (isset($compatibility['extensions']) && !is_array($compatibility['extensions'])) return null;
        return new self(
            $data['name'], $data['label'], $data['version'], $data['description'] ?? '', $path,
            is_array($data['navigation'] ?? null) ? $data['navigation'] : [],
            is_array($data['migrations'] ?? null) ? $data['migrations'] : [],
            is_array($data['assets'] ?? null) ? $data['assets'] : [],
            is_array($data['locales'] ?? null) ? $data['locales'] : [],
            false,
            is_string($compatibility['companyware'] ?? null) ? $compatibility['companyware'] : '*',
            is_string($compatibility['php'] ?? null) ? $compatibility['php'] : '*',
            is_array($compatibility['extensions'] ?? null) ? $compatibility['extensions'] : [],
            is_array($data['require'] ?? null) ? $data['require'] : [],
            is_array($data['optional'] ?? null) ? $data['optional'] : [],
            is_array($data['conflict'] ?? null) ? $data['conflict'] : [],
            is_array($data['provide'] ?? null) ? $data['provide'] : [],
            is_array($data['replace'] ?? null) ? $data['replace'] : [],
            is_array($data['settings'] ?? null) ? $data['settings'] : [],
            $uninstallData,
            is_array($data['entities']['references'] ?? null) ? $data['entities']['references'] : [],
            is_array($data['privileges'] ?? null) ? $data['privileges'] : [],
        );
    }

    public function namespace(): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->name)));
    }
}
