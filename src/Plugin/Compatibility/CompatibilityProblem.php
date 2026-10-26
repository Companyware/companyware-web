<?php

declare(strict_types=1);

namespace App\Plugin\Compatibility;

final readonly class CompatibilityProblem
{
    public function __construct(
        public string $code,
        public string $plugin,
        public string $message,
        public array $context = [],
    ) {
    }
}
