<?php

namespace App\Plugin;

final readonly class PluginManifest
{
    public function __construct(public string $name, public string $label, public string $version, public string $description, public string $path, public array $navigation = [], public array $migrations = [], public array $assets = [], public bool $core = false)
    {
    }

    public static function fromFile(string $file): ?self
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !preg_match('/^[a-z][a-z0-9-]+$/', $data['name'] ?? '')) return null;
        if (!is_string($data['label'] ?? null) || !is_string($data['version'] ?? null)) return null;
        return new self($data['name'], $data['label'], $data['version'], $data['description'] ?? '', dirname($file), is_array($data['navigation'] ?? null) ? $data['navigation'] : [], is_array($data['migrations'] ?? null) ? $data['migrations'] : [], is_array($data['assets'] ?? null) ? $data['assets'] : []);
    }

    public function namespace(): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->name)));
    }
}
