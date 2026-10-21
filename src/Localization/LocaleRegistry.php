<?php

declare(strict_types=1);

namespace App\Localization;

use App\Infrastructure\Database;
use App\Infrastructure\PluginRegistry;
use App\Infrastructure\Schema;
use Throwable;

final class LocaleRegistry
{
    private const CORE_LOCALES = ['de' => 'Deutsch', 'en' => 'English'];

    public function __construct(
        private readonly Database $database,
        private readonly Schema $schema,
        private readonly PluginRegistry $plugins,
    ) {
    }

    /** @return array<string, string> */
    public function available(): array
    {
        $locales = self::CORE_LOCALES;
        try {
            foreach ($this->plugins->activeManifests() as $plugin) {
                foreach ($plugin->locales as $locale) {
                    if (!is_array($locale)) continue;
                    $code = $locale['code'] ?? null;
                    $label = $locale['label'] ?? null;
                    if (is_string($code) && preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code) && is_string($label) && $label !== '') {
                        $locales[$code] = $label;
                    }
                }
            }
        } catch (Throwable) {
            // Core locales remain available while the database is unavailable.
        }

        return $locales;
    }

    public function current(): string
    {
        try {
            $this->schema->ensure();
            $statement = $this->database->connection()->query("SELECT config_value FROM system_config WHERE config_key = 'app.locale'");
            $locale = $statement->fetchColumn();
            return is_string($locale) && isset($this->available()[$locale]) ? $locale : 'de';
        } catch (Throwable) {
            return 'de';
        }
    }

    public function save(string $locale): void
    {
        if (!isset($this->available()[$locale])) {
            throw new \InvalidArgumentException('Unsupported locale.');
        }
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('INSERT INTO system_config (config_key, config_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
        $statement->execute(['key' => 'app.locale', 'value' => $locale]);
    }
}
