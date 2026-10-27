<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

use PDO;

/**
 * Low-level DBAL escape hatch for repository adapters and migrations.
 * Domain services must depend on repository contracts instead.
 *
 * @api
 */
interface ConnectionProviderInterface
{
    public function connection(): PDO;
}
