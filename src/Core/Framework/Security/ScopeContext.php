<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api since Companyware public API 1.0 */
final readonly class ScopeContext
{
    public function __construct(public int $actorId, public ?string $department) {}
}
