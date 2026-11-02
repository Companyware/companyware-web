<?php
declare(strict_types=1);
namespace App\Migration\Core;
use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;
final class Version202611020002 implements PluginMigrationInterface,IrreversibleMigrationInterface
{
    public function version():string{return '202611020002';}
    public function up(PDO $database):void
    {
        $database->exec('CREATE TABLE IF NOT EXISTS api_oauth_client (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_identifier VARCHAR(120) NOT NULL UNIQUE, client_name VARCHAR(150) NOT NULL, client_secret_hash CHAR(64) NOT NULL, admin_user_id INT UNSIGNED NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_api_oauth_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $database->exec('CREATE TABLE IF NOT EXISTS api_idempotency (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_user_id INT UNSIGNED NOT NULL, idempotency_key VARCHAR(150) NOT NULL, request_hash CHAR(64) NOT NULL, status_code SMALLINT UNSIGNED NOT NULL, response_body LONGTEXT NOT NULL, response_content_type VARCHAR(120) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL, UNIQUE KEY uniq_api_idempotency (admin_user_id,idempotency_key), INDEX idx_api_idempotency_expiry (expires_at), CONSTRAINT fk_api_idempotency_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    public function down(PDO $database):void{throw new \LogicException('OAuth clients and API idempotency records are protected security state.');}
    public function irreversibleReason():string{return 'OAuth clients and API request history are protected security state.';}
}
