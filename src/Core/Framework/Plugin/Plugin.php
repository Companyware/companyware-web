<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin;

use Companyware\Core\Framework\Plugin\Contract\PluginLifecycleInterface;
use Companyware\Core\Framework\Plugin\Contract\PluginUpdateInterface;

/**
 * Base class for Companyware plugins.
 *
 * Lifecycle methods are intentionally optional. Plugins override only the hooks
 * they need and obtain dependencies through ordinary Symfony injection.
 *
 * @api since Companyware public API 1.0
 */
abstract class Plugin implements PluginLifecycleInterface, PluginUpdateInterface
{
    public function install(): void
    {
    }

    public function activate(): void
    {
    }

    public function update(string $previousVersion): void
    {
    }

    public function deactivate(): void
    {
    }

    public function uninstall(): void
    {
    }
}
