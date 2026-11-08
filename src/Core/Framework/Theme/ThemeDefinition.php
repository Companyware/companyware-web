<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Theme;

/** @api since Companyware public API 1.0 */
final readonly class ThemeDefinition
{
    /** @param list<string> $stylesheets @param list<array<string,mixed>> $settings */
    public function __construct(public string $id, public string $label, public string $owner, public string $type, public array $stylesheets, public int $priority = 100, public array $settings = []) {}
}
