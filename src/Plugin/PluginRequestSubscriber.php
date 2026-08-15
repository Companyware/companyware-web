<?php
namespace App\Plugin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
final class PluginRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PluginTwigLoader $twig, private readonly \App\Infrastructure\PluginRegistry $plugins) {}
    public static function getSubscribedEvents(): array { return [KernelEvents::REQUEST=>['loadTwig',100], KernelEvents::CONTROLLER=>'guard']; }
    public function loadTwig(RequestEvent $event): void { if ($event->isMainRequest()) $this->twig->load(); }
    public function guard(ControllerEvent $event): void
    {
        $name=$event->getRequest()->attributes->get('_plugin');
        if (is_string($name) && !$this->plugins->isActive($name)) $event->setController(fn()=>new Response('Plugin is inactive.',404));
    }
}
