<?php
namespace App\Plugin\Migration;
use App\Plugin\PluginManifest;
use Companyware\Core\Framework\Plugin\Contract\Migration\MigrationStateProviderInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\IrreversibleMigrationInterface;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use ReflectionClass;
use RuntimeException;
final class MigrationManager implements MigrationStateProviderInterface
{
    public function __construct(private readonly MigrationStorage $storage) {}

    public function migrate(PluginManifest $plugin): void
    {
        $this->runForward($plugin->name, $this->instances($plugin));
    }

    /** @param list<class-string<PluginMigrationInterface>> $classes */
    public function migrateCore(array $classes): void
    {
        $migrations = [];
        foreach ($classes as $class) {
            $migration = new $class();
            if (!$migration instanceof PluginMigrationInterface) throw new RuntimeException($class.' must implement PluginMigrationInterface.');
            $migrations[] = $migration;
        }
        usort($migrations, static fn (PluginMigrationInterface $a, PluginMigrationInterface $b): int => strcmp($a->version(), $b->version()));
        $this->runForward('core-administration', $migrations, 'core');
    }

    public function rollback(PluginManifest $plugin, bool $installationRecovery = false): void
    {
        if (!$installationRecovery && $plugin->uninstallData === 'retain') return;

        $this->storage->acquire($plugin->name);
        try {
            foreach (array_reverse($this->instances($plugin)) as $migration) {
                $this->runDown($plugin->name, $migration);
            }
        } finally {
            $this->storage->release($plugin->name);
        }
    }

    public function states(?string $pluginName = null): array
    {
        return $this->storage->states($pluginName);
    }

    /** @return array<string, array{completed: int, failed: int, running: int}> */
    public function summary(): array
    {
        $summary = [];
        foreach ($this->states() as $state) {
            $summary[$state->pluginName] ??= ['completed' => 0, 'failed' => 0, 'running' => 0];
            if (isset($summary[$state->pluginName][$state->status])) $summary[$state->pluginName][$state->status]++;
        }

        return $summary;
    }

    /** @param list<PluginMigrationInterface> $migrations */
    private function runForward(string $owner, array $migrations, ?string $keyPrefix = null): void
    {
        $this->storage->acquire($owner);
        try {
            foreach ($migrations as $migration) {
                $key = ($keyPrefix ?? $owner).':'.$migration->version();
                $checksum = $this->checksum($migration);
                $record = $this->storage->record($key);
                if (($record['status'] ?? null) === 'completed') {
                    if (!empty($record['checksum']) && !hash_equals((string) $record['checksum'], $checksum)) {
                        throw new MigrationException(sprintf('Checksum mismatch for applied migration "%s".', $key));
                    }
                    if (empty($record['checksum'])) $this->storage->adoptChecksum($key, $checksum);
                    continue;
                }
                $this->execute($key, $owner, $migration, $checksum, 'up');
            }
        } finally {
            $this->storage->release($owner);
        }
    }

    private function runDown(string $owner, PluginMigrationInterface $migration): void
    {
        $key = $owner.':'.$migration->version();
        $record = $this->storage->record($key);
        if (!$record) return;
        if ($migration instanceof IrreversibleMigrationInterface) {
            throw new MigrationException(sprintf('Migration "%s" is irreversible: %s', $key, $migration->irreversibleReason()));
        }

        $checksum = $this->checksum($migration);
        if (($record['status'] ?? null) === 'completed' && !empty($record['checksum']) && !hash_equals((string) $record['checksum'], $checksum)) {
            throw new MigrationException(sprintf('Checksum mismatch for applied migration "%s".', $key));
        }
        $this->execute($key, $owner, $migration, $checksum, 'down');
        $this->storage->remove($key);
    }

    private function execute(string $key, string $owner, PluginMigrationInterface $migration, string $checksum, string $direction): void
    {
        $pdo = $this->storage->connection();
        $this->storage->running($key, $owner, $checksum, $direction);
        $pdo->beginTransaction();
        try {
            $direction === 'up' ? $migration->up($pdo) : $migration->down($pdo);
            if ($pdo->inTransaction()) $pdo->commit();
            $this->storage->completed($key, $checksum, $direction);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->storage->failed($key, $exception->getMessage(), $direction);
            throw new MigrationException(sprintf('Migration "%s" failed during %s: %s', $key, $direction, $exception->getMessage()), 0, $exception);
        }
    }

    private function checksum(PluginMigrationInterface $migration): string
    {
        $reflection = new ReflectionClass($migration);
        $file = $reflection->getFileName();
        if (!is_string($file) || !is_file($file)) {
            throw new MigrationException(sprintf('Cannot checksum migration "%s".', $reflection->getName()));
        }

        return hash('sha256', $migration::class."\0".$migration->version()."\0".(string) file_get_contents($file));
    }

    private function instances(PluginManifest $plugin): array
    {
        $result=[];
        foreach ($plugin->migrations as $class) {
            if (!is_string($class) || !class_exists($class)) throw new RuntimeException('Migration class not found: '.(string)$class);
            $migration=new $class();
            if (!$migration instanceof PluginMigrationInterface) throw new RuntimeException($class.' must implement PluginMigrationInterface.');
            $result[]=$migration;
        }
        usort($result,fn($a,$b)=>strcmp($a->version(),$b->version())); return $result;
    }
}
