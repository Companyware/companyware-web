<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract\Migration;

use PDO;

/** @api since Companyware public API 1.0 */
interface PluginMigrationInterface
{
    public function version(): string;

    public function up(PDO $database): void;

    public function down(PDO $database): void;
}
