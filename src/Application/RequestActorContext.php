<?php

declare(strict_types=1);

namespace App\Application;

use Companyware\Core\Framework\Application\ActorContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestActorContext implements ActorContextInterface
{
    public const ATTRIBUTE = '_companyware_actor_id';

    public function __construct(private readonly RequestStack $requests) {}

    public function actorId(): ?int
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) return null;
        $attribute = (int) $request->attributes->get(self::ATTRIBUTE, 0);
        if ($attribute > 0) return $attribute;
        $admin = $request->hasSession() ? $request->getSession()->get('admin') : null;
        $id = is_array($admin) ? (int) ($admin['id'] ?? 0) : 0;

        return $id > 0 ? $id : null;
    }
}
