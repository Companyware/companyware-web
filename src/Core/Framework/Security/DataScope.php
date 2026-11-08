<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Security;

/** @api since Companyware public API 1.0 */
enum DataScope: string
{
    case Own = 'own';
    case Department = 'department';
    case All = 'all';

    public function rank(): int
    {
        return match ($this) { self::Own => 1, self::Department => 2, self::All => 3 };
    }
}
