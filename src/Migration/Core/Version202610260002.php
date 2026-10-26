<?php

declare(strict_types=1);

namespace App\Migration\Core;

use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;

final class Version202610260002 implements PluginMigrationInterface, IrreversibleMigrationInterface
{
    public function version(): string
    {
        return '202610260002';
    }

    public function up(PDO $database): void
    {
        $database->exec('CREATE TABLE IF NOT EXISTS admin_preference (
            admin_user_id INT UNSIGNED NOT NULL,
            preference_key VARCHAR(190) NOT NULL,
            preference_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (admin_user_id, preference_key),
            CONSTRAINT fk_admin_preference_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(PDO $database): void
    {
        throw new \LogicException('Core administrator preferences are protected platform state.');
    }

    public function irreversibleReason(): string
    {
        return 'Core administrator preferences are retained as protected platform state.';
    }
}
