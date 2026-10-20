<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract;

/** @api since Companyware public API 1.0 */
interface SlotProviderInterface
{
    public function pluginName(): string;

    public function supports(string $slot): bool;

    /** @param array<string, mixed> $context */
    public function render(string $slot, array $context = []): string;
}
