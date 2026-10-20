<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract;

/** @api since Companyware public API 1.0 */
interface NavigationProviderInterface
{
    public function pluginName(): string;

    /** @return iterable<array{section: string, label: string, route: string, icon?: string, position?: int}> */
    public function items(): iterable;
}
