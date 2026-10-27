<?php

declare(strict_types=1);

namespace Plugins\Documents\Migration;

use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;

final class Version202610270001 implements PluginMigrationInterface
{
    public function version(): string { return '202610270001'; }

    public function up(PDO $database): void
    {
        $this->addColumn($database, 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addColumn($database, 'version', 'INT UNSIGNED NOT NULL DEFAULT 1');
        $this->addColumn($database, 'deleted_at', 'DATETIME NULL DEFAULT NULL');
        if (!$this->hasIndex($database, 'idx_document_active_created')) {
            $database->exec('CREATE INDEX idx_document_active_created ON document_file (deleted_at, created_at)');
        }
    }

    public function down(PDO $database): void
    {
        if ($this->hasIndex($database, 'idx_document_active_created')) $database->exec('DROP INDEX idx_document_active_created ON document_file');
        foreach (['deleted_at', 'version', 'updated_at'] as $column) {
            if ($this->hasColumn($database, $column)) $database->exec('ALTER TABLE document_file DROP COLUMN '.$column);
        }
    }

    private function addColumn(PDO $database, string $column, string $definition): void
    {
        if (!$this->hasColumn($database, $column)) $database->exec('ALTER TABLE document_file ADD COLUMN '.$column.' '.$definition);
    }

    private function hasColumn(PDO $database, string $column): bool
    {
        $statement = $database->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $statement->execute(['table' => 'document_file', 'column' => $column]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function hasIndex(PDO $database, string $index): bool
    {
        $statement = $database->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index');
        $statement->execute(['table' => 'document_file', 'index' => $index]);
        return (int) $statement->fetchColumn() > 0;
    }
}
