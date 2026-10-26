<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Event;

/** @api since Companyware public API 1.0 */
enum PluginLifecycleOperation: string
{
    case Install = 'install';
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case Uninstall = 'uninstall';
}
