<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api since Companyware public API 1.0 */
interface ScopedAuthorizationInterface extends AuthorizationInterface
{
    public function scope(string $privilege): ?DataScope;
}
