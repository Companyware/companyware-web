<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Api;

/**
 * Identifies the versioned public PHP contract exposed to plugins.
 *
 * @api
 */
final class PublicApi
{
    public const VERSION = '1.0';

    private function __construct()
    {
    }
}
