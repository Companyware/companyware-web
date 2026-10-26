<?php

declare(strict_types=1);

namespace App\Plugin\Compatibility;

use App\Plugin\PluginPackageException;

final class PluginCompatibilityException extends PluginPackageException
{
    /** @param list<CompatibilityProblem> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode("\n", array_map(
            static fn (CompatibilityProblem $problem): string => $problem->message,
            $problems,
        )));
    }
}
