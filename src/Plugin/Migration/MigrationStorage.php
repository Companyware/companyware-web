<?php

declare(strict_types=1);

namespace App\Plugin\Migration;

use App\Infrastructure\Database;
use Companyware\Core\Framework\Plugin\Contract\Migration\MigrationState;
use PDO;

final class MigrationStorage
{
    private bool $ready = false;

    public function __construct(private readonly Database $database)
    {
    }

    public function connection(): PDO
    {
        $this->ensure();

        return $this->database->connection();
    }

    public function ensure(): void
    {
        if ($this->ready) return;

        $pdo = $this->database->connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS migration (
            version VARCHAR(255) PRIMARY KEY,
            plugin_name VARCHAR(190) NOT NULL,
            checksum CHAR(64) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'completed\',
            direction VARCHAR(12) NOT NULL DEFAULT \'up\',
            error_message TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            finished_at DATETIME DEFAULT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration_plugin (plugin_name),
            INDEX idx_migration_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $bootstrapLock = 'companyware:migration:storage';
        $lock = $pdo->prepare('SELECT GET_LOCK(:name, 10)');
        $lock->execute(['name' => $bootstrapLock]);
        if ((int) $lock->fetchColumn() !== 1) throw new MigrationException('Could not acquire migration storage lock.');
        try {
            $columns = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migration'")->fetchAll(PDO::FETCH_COLUMN);
            $definitions = [
                'checksum' => 'CHAR(64) DEFAULT NULL',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'completed'",
                'direction' => "VARCHAR(12) NOT NULL DEFAULT 'up'",
                'error_message' => 'TEXT DEFAULT NULL',
                'started_at' => 'DATETIME DEFAULT NULL',
                'finished_at' => 'DATETIME DEFAULT NULL',
            ];
            foreach ($definitions as $column => $definition) {
                if (!in_array($column, $columns, true)) {
                    $pdo->exec(sprintf('ALTER TABLE migration ADD COLUMN %s %s', $column, $definition));
                }
            }
            $pdo->exec("UPDATE migration SET status='completed', direction='up', finished_at=COALESCE(finished_at, executed_at) WHERE status IS NULL OR status='' OR (status='completed' AND finished_at IS NULL)");
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $bootstrapLock]);
        }
        $this->ready = true;
    }

    public function acquire(string $owner, int $timeout = 10): void
    {
        $statement = $this->connection()->prepare('SELECT GET_LOCK(:name, :timeout)');
        $statement->execute(['name' => $this->lockName($owner), 'timeout' => $timeout]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new MigrationException(sprintf('Could not acquire migration lock for "%s".', $owner));
        }
    }

    public function release(string $owner): void
    {
        $statement = $this->connection()->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute(['name' => $this->lockName($owner)]);
    }

    public function record(string $key): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM migration WHERE version=:version');
        $statement->execute(['version' => $key]);
        $record = $statement->fetch();

        return is_array($record) ? $record : null;
    }

    public function running(string $key, string $owner, string $checksum, string $direction): void
    {
        $statement = $this->connection()->prepare('INSERT INTO migration (version, plugin_name, checksum, status, direction, error_message, started_at, finished_at)
            VALUES (:version, :plugin, :checksum, \'running\', :direction, NULL, NOW(), NULL)
            ON DUPLICATE KEY UPDATE plugin_name=VALUES(plugin_name), checksum=VALUES(checksum), status=\'running\', direction=VALUES(direction), error_message=NULL, started_at=NOW(), finished_at=NULL');
        $statement->execute(['version' => $key, 'plugin' => $owner, 'checksum' => $checksum, 'direction' => $direction]);
    }

    public function completed(string $key, string $checksum, string $direction): void
    {
        $statement = $this->connection()->prepare('UPDATE migration SET checksum=:checksum, status=\'completed\', direction=:direction, error_message=NULL, finished_at=NOW(), executed_at=NOW() WHERE version=:version');
        $statement->execute(['version' => $key, 'checksum' => $checksum, 'direction' => $direction]);
    }

    public function adoptChecksum(string $key, string $checksum): void
    {
        $statement = $this->connection()->prepare('UPDATE migration SET checksum=:checksum, finished_at=COALESCE(finished_at, executed_at) WHERE version=:version AND checksum IS NULL');
        $statement->execute(['version' => $key, 'checksum' => $checksum]);
    }

    public function failed(string $key, string $error, string $direction): void
    {
        $statement = $this->connection()->prepare('UPDATE migration SET status=\'failed\', direction=:direction, error_message=:error, finished_at=NOW() WHERE version=:version');
        $statement->execute(['version' => $key, 'direction' => $direction, 'error' => substr($error, 0, 4000)]);
    }

    public function remove(string $key): void
    {
        $statement = $this->connection()->prepare('DELETE FROM migration WHERE version=:version');
        $statement->execute(['version' => $key]);
    }

    /** @return list<MigrationState> */
    public function states(?string $owner = null): array
    {
        if ($owner === null) {
            $rows = $this->connection()->query('SELECT * FROM migration ORDER BY plugin_name, version')->fetchAll();
        } else {
            $statement = $this->connection()->prepare('SELECT * FROM migration WHERE plugin_name=:plugin ORDER BY version');
            $statement->execute(['plugin' => $owner]);
            $rows = $statement->fetchAll();
        }

        return array_map(static fn (array $row): MigrationState => new MigrationState(
            (string) $row['version'],
            (string) $row['plugin_name'],
            (string) $row['status'],
            (string) $row['direction'],
            isset($row['checksum']) ? (string) $row['checksum'] : null,
            isset($row['error_message']) ? (string) $row['error_message'] : null,
            isset($row['started_at']) ? (string) $row['started_at'] : null,
            isset($row['finished_at']) ? (string) $row['finished_at'] : null,
        ), $rows);
    }

    private function lockName(string $owner): string
    {
        return 'companyware:migration:'.substr(hash('sha256', $owner), 0, 32);
    }
}
