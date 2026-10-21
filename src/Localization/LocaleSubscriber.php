<?php

declare(strict_types=1);

namespace App\Localization;

use App\Infrastructure\PluginRegistry;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private bool $pluginResourcesLoaded = false;

    public function __construct(
        private readonly LocaleRegistry $locales,
        private readonly PluginRegistry $plugins,
        private readonly Translator $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 100]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;
        $this->loadPluginResources();
        $event->getRequest()->setLocale($this->locales->current());
    }

    private function loadPluginResources(): void
    {
        if ($this->pluginResourcesLoaded) return;
        try {
            foreach ($this->plugins->manifests() as $plugin) {
                foreach (glob($plugin->path.'/translations/messages.*.yaml') ?: [] as $file) {
                    if (preg_match('/messages\.([a-z]{2}(?:_[A-Z]{2})?)\.yaml$/', $file, $matches)) {
                        $this->translator->addResource('yaml', $file, $matches[1]);
                    }
                }
            }
        } catch (\Throwable) {
            // Login remains available with Core translations if plugin discovery fails.
        }
        $this->pluginResourcesLoaded = true;
    }
}
