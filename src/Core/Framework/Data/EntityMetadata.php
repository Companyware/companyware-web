<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

/** @api */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class EntityMetadata
{
    public function __construct(
        public string $owner,
        public bool $softDelete = true,
    ) {
        if (!preg_match('/^(?:core|[a-z][a-z0-9-]+)$/', $owner)) {
            throw new \InvalidArgumentException(sprintf('Invalid entity owner "%s".', $owner));
        }
    }
}
