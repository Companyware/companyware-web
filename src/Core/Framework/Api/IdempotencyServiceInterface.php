<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @api */
interface IdempotencyServiceInterface
{
    /** @param callable(): Response $operation */
    public function execute(Request $request, callable $operation): Response;
}
