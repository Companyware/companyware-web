<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract\Migration;

/**
 * Marks a forward migration that cannot safely remove its changes.
 *
 * @api since Companyware public API 1.0
 */
interface IrreversibleMigrationInterface
{
    public function irreversibleReason(): string;
}
