<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract\Migration;

/** @api since Companyware public API 1.0 */
interface MigrationStateProviderInterface
{
    /** @return list<MigrationState> */
    public function states(?string $pluginName = null): array;
}
