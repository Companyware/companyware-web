<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract;

/** @api since Companyware public API 1.0 */
interface PluginLifecycleInterface
{
    public function install(): void;

    public function activate(): void;

    public function deactivate(): void;

    public function uninstall(): void;
}
