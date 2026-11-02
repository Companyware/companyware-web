<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Application;

/** @api since Companyware public API 1.0 */
final readonly class PageResult
{
    /** @param list<mixed> $items */
    public function __construct(public array $items, public int $total, public int $limit, public int $offset) {}
}
