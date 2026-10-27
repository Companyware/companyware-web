<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api */
interface PrivilegeAwareInterface
{
    public function requiredPrivilege(string $extensionPoint): string;
}
