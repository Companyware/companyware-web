<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Data;

/**
 * Persistence-neutral query boundary. Repositories own the supported field names.
 *
 * @api
 */
final readonly class EntityQuery
{
    /**
     * @param array<string, scalar|null> $filters
     * @param array<string, 'asc'|'desc'> $orderBy
     */
    public function __construct(
        public array $filters = [],
        public array $orderBy = [],
        public int $limit = 50,
        public int $offset = 0,
        public bool $includeDeleted = false,
    ) {
        if ($limit < 1 || $limit > 500 || $offset < 0) {
            throw new \InvalidArgumentException('Entity query limit or offset is invalid.');
        }
        foreach ($orderBy as $direction) {
            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new \InvalidArgumentException('Entity query sort direction is invalid.');
            }
        }
    }
}
