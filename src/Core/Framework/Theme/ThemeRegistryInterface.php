<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Theme;

/** @api since Companyware public API 1.0 */
interface ThemeRegistryInterface
{
    /** @return list<ThemeDefinition> */
    public function themes(): array;
    public function selected(): ThemeDefinition;
    /** @return list<string> */
    public function stylesheets(): array;
    public function select(string $themeId): void;
}
