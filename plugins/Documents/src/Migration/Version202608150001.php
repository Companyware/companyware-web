<?php
namespace Plugins\Documents\Migration;
use App\Plugin\Migration\PluginMigrationInterface;
use PDO;
final class Version202608150001 implements PluginMigrationInterface
{
    public function version(): string { return '202608150001'; }
    public function up(PDO $database): void { $database->exec('CREATE TABLE IF NOT EXISTS document_file (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(64) NOT NULL UNIQUE, mime_type VARCHAR(120) NOT NULL, file_size INT UNSIGNED NOT NULL, uploaded_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_document_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    public function down(PDO $database): void { $database->exec('DROP TABLE IF EXISTS document_file'); }
}
