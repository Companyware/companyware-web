<?php

declare(strict_types=1);

namespace App\Migration\Core;

use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use PDO;

final class Version202610260001 implements PluginMigrationInterface, IrreversibleMigrationInterface
{
    public function version(): string
    {
        return '202610260001';
    }

    public function up(PDO $database): void
    {
        $database->exec('CREATE TABLE IF NOT EXISTS plugin_config (
            plugin_name VARCHAR(190) NOT NULL,
            config_key VARCHAR(190) NOT NULL,
            config_value TEXT DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (plugin_name, config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(PDO $database): void
    {
        throw new \LogicException('Core migrations cannot be uninstalled.');
    }

    public function irreversibleReason(): string
    {
        return 'Core plugin configuration is retained as protected platform state.';
    }
}
