<?php

declare(strict_types=1);

namespace App\Security;

use Companyware\Core\Framework\Security\AuthorizationInterface;
use Companyware\Core\Framework\Security\RequiresPrivilege;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuthorizationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationInterface $authorization) {}
    public static function getSubscribedEvents(): array { return [KernelEvents::CONTROLLER => ['authorize', -50]]; }

    public function authorize(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller) || !is_object($controller[0]) || !is_string($controller[1])) return;
        $class = new \ReflectionClass($controller[0]);
        $method = $class->getMethod($controller[1]);
        foreach ([...$class->getAttributes(RequiresPrivilege::class), ...$method->getAttributes(RequiresPrivilege::class)] as $attribute) {
            $this->authorization->denyUnlessGranted($attribute->newInstance()->privilege);
        }
    }
}
