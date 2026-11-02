<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\Application;

/**
 * Identifies the authenticated application actor independently of HTTP, CLI or API transport.
 *
 * @api since Companyware public API 1.0
 */
interface ActorContextInterface
{
    public function actorId(): ?int;
}
