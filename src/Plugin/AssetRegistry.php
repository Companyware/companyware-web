<?php
namespace App\Plugin;

use App\Infrastructure\PluginRegistry;

final class AssetRegistry
{
    private readonly string $publicDirectory;

    public function __construct(private readonly PluginRegistry $plugins)
    {
        $this->publicDirectory = dirname(__DIR__, 2).'/public';
    }

    public function stylesheets(): array
    {
        return $this->resolve('styles', [
            ['handle' => 'core-admin', 'url' => $this->versioned('/assets/admin.css'), 'priority' => 0],
            ['handle' => 'core-plugin-manager', 'url' => $this->versioned('/assets/plugin-manager.css'), 'priority' => 10],
        ]);
    }

    public function javascripts(): array
    {
        return $this->resolve('scripts', [
            ['handle' => 'core-admin-js', 'url' => $this->versioned('/assets/admin.js'), 'priority' => 0, 'defer' => true],
        ]);
    }

    private function versioned(string $url): string
    {
        $path = $this->publicDirectory.str_replace('/', DIRECTORY_SEPARATOR, $url);
        $version = is_file($path) ? (string) filemtime($path) : '1';

        return $url.'?v='.$version;
    }

    private function resolve(string $type, array $assets): array
    {
        foreach ($this->plugins->activeManifests() as $plugin) {
            foreach ($plugin->assets[$type] ?? [] as $asset) {
                if (!is_array($asset) || !is_string($asset['handle'] ?? null) || !is_string($asset['path'] ?? null)) {
                    continue;
                }
                $assets[] = $asset + [
                    'priority' => 100,
                    'defer' => true,
                    'url' => '/plugin-assets/'.rawurlencode($plugin->name).'?path='.rawurlencode($asset['path']),
                ];
            }
        }

        $replaced = [];
        foreach ($assets as $asset) {
            foreach ((array) ($asset['replaces'] ?? []) as $handle) {
                if (is_string($handle)) {
                    $replaced[$handle] = true;
                }
            }
        }

        $assets = array_values(array_filter($assets, fn (array $asset): bool => !isset($replaced[$asset['handle']])));
        usort($assets, fn (array $a, array $b): int => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

        return $assets;
    }
}
