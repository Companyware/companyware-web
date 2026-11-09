<?php

declare(strict_types=1);

namespace App\Migration\Core;

use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;

final class Version202611090001 implements PluginMigrationInterface, IrreversibleMigrationInterface
{
    public function version(): string { return '202611090001'; }

    public function up(PDO $database): void
    {
        $database->exec('ALTER TABLE admin_user ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash');
        $database->exec('ALTER TABLE admin_user ADD COLUMN IF NOT EXISTS email_normalized VARCHAR(190) NULL AFTER email');
        $database->exec('ALTER TABLE admin_user ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER email_normalized');
        $index = $database->query("SHOW INDEX FROM admin_user WHERE Key_name = 'uniq_admin_recovery_email'");
        if ($index === false || $index->fetch() === false) $database->exec('ALTER TABLE admin_user ADD UNIQUE INDEX uniq_admin_recovery_email (email_normalized)');
        $database->exec('CREATE TABLE IF NOT EXISTS account_recovery_key (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recovery_user (admin_user_id),
            INDEX idx_recovery_expiry (expires_at),
            CONSTRAINT fk_recovery_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS account_recovery_rate_limit (
            bucket_hash CHAR(64) NOT NULL,
            window_start DATETIME NOT NULL,
            attempt_count INT UNSIGNED NOT NULL,
            PRIMARY KEY (bucket_hash, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS account_email_verification (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT UNSIGNED NOT NULL,
            email_normalized VARCHAR(190) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_verification_user (admin_user_id),
            CONSTRAINT fk_email_verification_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(PDO $database): void { throw new \LogicException('Account recovery state is protected security data.'); }
    public function irreversibleReason(): string { return 'Account recovery and account activation state are protected security data.'; }
}
