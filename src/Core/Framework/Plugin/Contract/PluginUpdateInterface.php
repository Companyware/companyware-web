<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract;

/** @api since Companyware public API 1.0 */
interface PluginUpdateInterface
{
    public function update(string $previousVersion): void;
}
