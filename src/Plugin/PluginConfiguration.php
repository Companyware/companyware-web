<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use Companyware\Core\Framework\Plugin\Contract\PluginConfigurationInterface;

final class PluginConfiguration implements PluginConfigurationInterface
{
    public function __construct(private readonly Database $database, private readonly Schema $schema)
    {
    }

    public function get(string $pluginName, string $key, mixed $default = null): mixed
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('SELECT config_value FROM plugin_config WHERE plugin_name=:plugin AND config_key=:key');
        $statement->execute(['plugin' => $pluginName, 'key' => $key]);
        $value = $statement->fetchColumn();

        return is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $default;
    }

    public function values(PluginManifest $plugin): array
    {
        $values = [];
        foreach ($plugin->settings as $field) {
            $values[$field['key']] = $this->get($plugin->name, $field['key'], $field['default'] ?? null);
        }

        return $values;
    }

    public function save(PluginManifest $plugin, array $input): void
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('INSERT INTO plugin_config (plugin_name, config_key, config_value) VALUES (:plugin,:key,:value) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)');
        foreach ($plugin->settings as $field) {
            $value = $this->normalize($field, $input[$field['key']] ?? null);
            $statement->execute(['plugin' => $plugin->name, 'key' => $field['key'], 'value' => json_encode($value, JSON_THROW_ON_ERROR)]);
        }
    }

    public function delete(string $pluginName): void
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('DELETE FROM plugin_config WHERE plugin_name=:plugin');
        $statement->execute(['plugin' => $pluginName]);
    }

    private function normalize(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'checkbox' => in_array($value, ['1', 1, true, 'true', 'on'], true),
            'number' => $this->number($field, $value),
            'select' => $this->selection($field, $value),
            default => substr(trim((string) $value), 0, (int) ($field['maxLength'] ?? 4000)),
        };
    }

    private function number(array $field, mixed $value): int|float
    {
        if (!is_numeric($value)) {
            $value = $field['default'] ?? 0;
        }
        $number = (float) $value;
        if (isset($field['min'])) $number = max($number, (float) $field['min']);
        if (isset($field['max'])) $number = min($number, (float) $field['max']);

        return floor($number) === $number ? (int) $number : $number;
    }

    private function selection(array $field, mixed $value): string
    {
        $allowed = array_map(static fn (array $option): string => (string) $option['value'], $field['options']);
        $value = (string) $value;

        return in_array($value, $allowed, true) ? $value : (string) ($field['default'] ?? $allowed[0]);
    }
}
