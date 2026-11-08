<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api since Companyware public API 1.0 */
interface ScopeContextInterface
{
    public function current(): ?ScopeContext;
}
