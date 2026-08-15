<?php

namespace App\Plugin;

final readonly class PluginManifest
{
    public function __construct(public string $name, public string $label, public string $version, public string $description, public string $path, public array $navigation = [], public bool $core = false)
    {
    }

    public static function fromFile(string $file): ?self
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !preg_match('/^[a-z][a-z0-9-]+$/', $data['name'] ?? '')) return null;
        return new self($data['name'], $data['label'] ?? $data['name'], $data['version'] ?? 'dev', $data['description'] ?? '', dirname($file), $data['navigation'] ?? []);
    }

    public function namespace(): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->name)));
    }
}
