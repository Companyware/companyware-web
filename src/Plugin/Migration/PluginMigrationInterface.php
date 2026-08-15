<?php
namespace App\Plugin\Migration;
use PDO;
interface PluginMigrationInterface
{
    public function version(): string;
    public function up(PDO $database): void;
    public function down(PDO $database): void;
}
