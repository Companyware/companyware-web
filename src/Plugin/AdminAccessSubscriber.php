<?php
namespace App\Plugin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
final class AdminAccessSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array { return [KernelEvents::REQUEST=>['guard',50]]; }
    public function guard(RequestEvent $event): void
    {
        $request=$event->getRequest(); if($event->isMainRequest() && str_starts_with($request->getPathInfo(),'/admin/') && !$request->getSession()->has('admin')) $event->setResponse(new RedirectResponse('/login'));
    }
}
