<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Theme;

/** @api since Companyware public API 1.0 */
interface ColorSchemeInterface
{
    /** Returns system, light or dark. */
    public function mode(): string;
    public function selectMode(string $mode): void;
}
