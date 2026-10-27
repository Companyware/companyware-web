<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api */
interface AuthorizationInterface
{
    public function isGranted(string $privilege): bool;

    public function denyUnlessGranted(string $privilege): void;
}
