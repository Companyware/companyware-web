<?php
declare(strict_types=1);
namespace App\Migration\Core;
use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;
final class Version202611020001 implements PluginMigrationInterface, IrreversibleMigrationInterface
{
    public function version():string{return '202611020001';}
    public function up(PDO $database):void{$database->exec('CREATE TABLE IF NOT EXISTS api_access_token (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_user_id INT UNSIGNED NOT NULL, token_name VARCHAR(150) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NULL, last_used_at DATETIME NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_api_token_user (admin_user_id), CONSTRAINT fk_api_token_user FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');}
    public function down(PDO $database):void{throw new \LogicException('API credentials are protected platform security state.');}
    public function irreversibleReason():string{return 'API credential history is protected platform security state.';}
}
