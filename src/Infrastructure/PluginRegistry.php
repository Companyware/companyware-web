<?php

namespace App\Infrastructure;

use App\Plugin\PluginManifest;

final class PluginRegistry
{
    public function __construct(private readonly Database $database, private readonly Schema $schema)
    {
    }

    public function all(): array
    {
        $this->schema->ensure();
        $plugins = [[
            'name' => 'core-administration',
            'label' => 'Core Administration',
            'description' => 'Login, Navigation, Profil und Systemeinstellungen.',
            'version' => '1.0.0',
            'active' => true,
            'core' => true,
        ]];

        $root = dirname(__DIR__, 2).'/plugins';
        foreach (glob($root.'/*/plugin.json') ?: [] as $manifest) {
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
        $root = dirname(__DIR__, 2).'/plugins';
        $manifests = [];
        foreach (glob($root.'/*/plugin.json') ?: [] as $file) {
            if ($manifest = PluginManifest::fromFile($file)) $manifests[] = $manifest;
        }
        return $manifests;
    }

    /** @return list<PluginManifest> */
    public function activeManifests(): array
    {
        return array_values(array_filter($this->manifests(), fn(PluginManifest $plugin) => $this->isActive($plugin->name)));
    }

    public function isActive(string $name): bool
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('SELECT active FROM plugin WHERE name = :name');
        $statement->execute(['name' => $name]);
        return (bool) $statement->fetchColumn();
    }

    public function toggle(string $name): bool
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('INSERT INTO plugin (name, active) VALUES (:name, 1)
            ON DUPLICATE KEY UPDATE active = NOT active');
        $statement->execute(['name' => $name]);
        return $this->isActive($name);
    }
}
