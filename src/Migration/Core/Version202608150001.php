<?php

declare(strict_types=1);

namespace App\Migration\Core;

use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use PDO;

final class Version202608150001 implements PluginMigrationInterface, IrreversibleMigrationInterface
{
    public function version(): string
    {
        return '202608150001';
    }

    public function up(PDO $database): void
    {
        $database->exec('CREATE TABLE IF NOT EXISTS admin_user (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(150) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS plugin (
            name VARCHAR(190) PRIMARY KEY,
            active TINYINT(1) NOT NULL DEFAULT 0,
            installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS system_config (
            config_key VARCHAR(190) PRIMARY KEY,
            config_value TEXT DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(PDO $database): void
    {
        throw new \LogicException('Core migrations cannot be uninstalled.');
    }

    public function irreversibleReason(): string
    {
        return 'Core identity and configuration tables are protected platform state.';
    }
}
