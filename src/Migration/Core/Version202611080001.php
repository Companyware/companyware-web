<?php

declare(strict_types=1);

namespace App\Migration\Core;

use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;

final class Version202611080001 implements PluginMigrationInterface
{
    public function version(): string { return '202611080001'; }
    public function up(PDO $database): void
    {
        $database->exec("ALTER TABLE admin_user ADD COLUMN IF NOT EXISTS department_key VARCHAR(100) NULL AFTER email");
        $database->exec("ALTER TABLE privilege ADD COLUMN IF NOT EXISTS supported_scopes VARCHAR(100) NOT NULL DEFAULT 'all'");
        $database->exec("ALTER TABLE admin_role_privilege ADD COLUMN IF NOT EXISTS data_scope VARCHAR(20) NOT NULL DEFAULT 'all'");
    }
    public function down(PDO $database): void {}
}
