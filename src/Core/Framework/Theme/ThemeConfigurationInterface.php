<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Theme;

/** @api since Companyware public API 1.0 */
interface ThemeConfigurationInterface
{
    /** @return array<string,mixed> */
    public function values(ThemeDefinition $theme): array;
    public function save(ThemeDefinition $theme, array $input): void;
}
