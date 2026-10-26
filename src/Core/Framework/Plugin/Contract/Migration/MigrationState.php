<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract\Migration;

/** @api since Companyware public API 1.0 */
final readonly class MigrationState
{
    public function __construct(
        public string $version,
        public string $pluginName,
        public string $status,
        public string $direction,
        public ?string $checksum,
        public ?string $error,
        public ?string $startedAt,
        public ?string $finishedAt,
    ) {
    }
}
