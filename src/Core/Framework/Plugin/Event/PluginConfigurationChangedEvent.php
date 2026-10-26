<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Event;

/** @api since Companyware public API 1.0 */
final readonly class PluginConfigurationChangedEvent
{
    /** @param list<string> $changedKeys */
    public function __construct(public string $pluginName, public array $changedKeys)
    {
    }
}
