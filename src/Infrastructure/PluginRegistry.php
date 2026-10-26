<?php

namespace App\Infrastructure;

use App\Plugin\PluginManifest;
use App\Plugin\PluginPackageLocator;

final class PluginRegistry
{
    public function __construct(private readonly Database $database, private readonly Schema $schema, private readonly PluginPackageLocator $packages)
    {
    }

    public function all(): array
    {
        $this->schema->ensure();
        $plugins = [[
            'name' => 'core-administration',
            'label' => 'Companyware Core',
            'description' => 'plugins.core.description',
            'version' => '1.0.0',
            'active' => true,
            'core' => true,
        ]];

        foreach ($this->packages->directories() as $directory) {
            $manifest = $directory.'/plugin.json';
            $data = json_decode((string) file_get_contents($manifest), true);
            if (!is_array($data) || empty($data['name'])) {
                continue;
            }
            $statement = $this->database->connection()->prepare('SELECT active FROM plugin WHERE name = :name');
            $statement->execute(['name' => $data['name']]);
            $state = $statement->fetch();
            $plugins[] = array_merge([
                'label' => $data['name'], 'description' => '', 'version' => 'dev', 'core' => false,
            ], $data, ['active' => (bool) ($state['active'] ?? false)]);
        }
        return $plugins;
    }

    /** @return list<PluginManifest> */
    public function manifests(): array
    {
        $manifests = [];
        foreach ($this->packages->directories() as $directory) {
            $file = $directory.'/plugin.json';
            if ($manifest = PluginManifest::fromFile($file)) $manifests[] = $manifest;
        }
        return $manifests;
    }

    /** @return list<PluginManifest> */
    public function activeManifests(): array
    {
        return array_values(array_filter($this->manifests(), fn(PluginManifest $plugin) => $this->isActive($plugin->name)));
    }

    public function manifest(string $name): ?PluginManifest
    {
        foreach ($this->manifests() as $manifest) if ($manifest->name===$name) return $manifest;
        return null;
    }

    public function isActive(string $name): bool
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('SELECT active FROM plugin WHERE name = :name');
        $statement->execute(['name' => $name]);
        return (bool) $statement->fetchColumn();
    }

    public function setActive(string $name, bool $active): void
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('INSERT INTO plugin (name, active) VALUES (:name, :active)
            ON DUPLICATE KEY UPDATE active = VALUES(active)');
        $statement->execute(['name' => $name, 'active' => $active ? 1 : 0]);
    }
}
