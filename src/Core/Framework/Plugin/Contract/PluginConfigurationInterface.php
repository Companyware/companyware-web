<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract;

/** @api since Companyware public API 1.0 */
interface PluginConfigurationInterface
{
    public function get(string $pluginName, string $key, mixed $default = null): mixed;
}
