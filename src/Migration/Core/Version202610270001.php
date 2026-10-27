<?php

declare(strict_types=1);

namespace App\Migration\Core;

use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;

final class Version202610270001 implements PluginMigrationInterface, IrreversibleMigrationInterface
{
    public function version(): string { return '202610270001'; }

    public function up(PDO $database): void
    {
        $database->exec('CREATE TABLE IF NOT EXISTS admin_role (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(150) NOT NULL, system_name VARCHAR(100) NULL UNIQUE, protected TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS privilege (privilege_key VARCHAR(190) PRIMARY KEY, owner VARCHAR(100) NOT NULL, label VARCHAR(190) NOT NULL, privilege_group VARCHAR(100) NOT NULL, protected TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_privilege_owner (owner)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS admin_role_privilege (role_id INT UNSIGNED NOT NULL, privilege_key VARCHAR(190) NOT NULL, PRIMARY KEY (role_id, privilege_key), CONSTRAINT fk_role_privilege_role FOREIGN KEY (role_id) REFERENCES admin_role (id) ON DELETE CASCADE, CONSTRAINT fk_role_privilege_key FOREIGN KEY (privilege_key) REFERENCES privilege (privilege_key) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS admin_user_role (admin_user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, PRIMARY KEY (admin_user_id, role_id), CONSTRAINT fk_user_role_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE, CONSTRAINT fk_user_role_role FOREIGN KEY (role_id) REFERENCES admin_role (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS security_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_admin_id INT UNSIGNED NULL, action_name VARCHAR(190) NOT NULL, target_type VARCHAR(100) NOT NULL, target_id VARCHAR(190) NULL, context_json TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_security_audit_created (created_at), CONSTRAINT fk_security_audit_actor FOREIGN KEY (actor_admin_id) REFERENCES admin_user (id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $core = [
            ['core.dashboard.customize','security.privilege.dashboard_customize','core'],
            ['core.plugins.manage','security.privilege.plugins','management'], ['core.settings.manage','security.privilege.settings','management'],
            ['core.security.manage','security.privilege.security','management'], ['core.profile.edit','security.privilege.profile','account'],
            ['core.recovery','security.privilege.recovery','protected'],
        ];
        $statement = $database->prepare('INSERT INTO privilege (privilege_key, owner, label, privilege_group, protected) VALUES (:key, :owner, :label, :group, :protected) ON DUPLICATE KEY UPDATE label = VALUES(label), privilege_group = VALUES(privilege_group), protected = VALUES(protected)');
        foreach ($core as [$key,$label,$group]) $statement->execute(['key'=>$key,'owner'=>'core','label'=>$label,'group'=>$group,'protected'=>$key==='core.recovery'?1:0]);
        $database->exec("INSERT INTO admin_role (role_name, system_name, protected) VALUES ('System administrator', 'system-administrator', 1) ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), protected = 1");
        $database->exec("INSERT IGNORE INTO admin_user_role (admin_user_id, role_id) SELECT u.id, r.id FROM admin_user u CROSS JOIN admin_role r WHERE r.system_name = 'system-administrator'");
    }

    public function down(PDO $database): void { throw new \LogicException('Core authorization and audit state is protected.'); }
    public function irreversibleReason(): string { return 'Roles, assignments and security audit history are protected platform state.'; }
}
