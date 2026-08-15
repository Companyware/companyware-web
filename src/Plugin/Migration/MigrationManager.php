<?php
namespace App\Plugin\Migration;
use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use App\Plugin\PluginManifest;
use RuntimeException;
final class MigrationManager
{
    public function __construct(private readonly Database $database, private readonly Schema $schema) {}
    public function migrate(PluginManifest $plugin): void
    {
        $this->schema->ensure(); $pdo=$this->database->connection();
        foreach ($this->instances($plugin) as $migration) {
            $key=$plugin->name.':'.$migration->version();
            $check=$pdo->prepare('SELECT 1 FROM migration WHERE version = :version'); $check->execute(['version'=>$key]);
            if ($check->fetchColumn()) continue;
            $pdo->beginTransaction();
            try { $migration->up($pdo); $write=$pdo->prepare('INSERT INTO migration (version, plugin_name) VALUES (:version,:plugin)'); $write->execute(['version'=>$key,'plugin'=>$plugin->name]); if($pdo->inTransaction())$pdo->commit(); }
            catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }
    }
    public function rollback(PluginManifest $plugin): void
    {
        $this->schema->ensure(); $pdo=$this->database->connection(); $migrations=array_reverse($this->instances($plugin));
        foreach ($migrations as $migration) {
            $key=$plugin->name.':'.$migration->version();
            $check=$pdo->prepare('SELECT 1 FROM migration WHERE version = :version'); $check->execute(['version'=>$key]);
            if (!$check->fetchColumn()) continue;
            $pdo->beginTransaction();
            try { $migration->down($pdo); $delete=$pdo->prepare('DELETE FROM migration WHERE version=:version'); $delete->execute(['version'=>$key]); if($pdo->inTransaction())$pdo->commit(); }
            catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }
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
