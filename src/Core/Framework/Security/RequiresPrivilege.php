<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class RequiresPrivilege
{
    public function __construct(public string $privilege)
    {
        if (!preg_match('/^[a-z][a-z0-9-]*\.[a-z][a-z0-9._-]*$/', $privilege)) {
            throw new \InvalidArgumentException(sprintf('Invalid privilege "%s".', $privilege));
        }
    }
}
