<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Application;

/** @api since Companyware public API 1.0 */
final readonly class PageRequest
{
    public function __construct(public int $limit = 50, public int $offset = 0)
    {
        if ($limit < 1 || $limit > 500 || $offset < 0) throw new \InvalidArgumentException('Page limit or offset is invalid.');
    }
}
