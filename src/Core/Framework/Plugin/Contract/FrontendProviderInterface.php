<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Plugin\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows an active plugin to own the public site entry point while /admin remains Core-owned.
 *
 * Plugins may additionally declare guarded public routes in their routes.yaml. Core security,
 * login and recovery paths must never be shadowed by a frontend provider.
 *
 * @api
 */
interface FrontendProviderInterface
{
    public function pluginName(): string;

    public function supports(Request $request): bool;

    public function render(Request $request): Response;

    public function priority(): int;
}
