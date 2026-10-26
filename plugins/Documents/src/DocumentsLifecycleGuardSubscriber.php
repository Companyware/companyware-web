<?php

declare(strict_types=1);

namespace Plugins\Documents;

use Companyware\Core\Framework\Plugin\Contract\PluginConfigurationInterface;
use Companyware\Core\Framework\Plugin\Event\BeforePluginLifecycleEvent;
use Companyware\Core\Framework\Plugin\Event\PluginLifecycleOperation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DocumentsLifecycleGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PluginConfigurationInterface $configuration)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [BeforePluginLifecycleEvent::class => ['validateConfiguration', 100]];
    }

    public function validateConfiguration(BeforePluginLifecycleEvent $event): void
    {
        if ($event->pluginName !== 'documents' || $event->operation !== PluginLifecycleOperation::Activate) {
            return;
        }
        $limit = (int) $this->configuration->get('documents', 'max_upload_mb', 15);
        if ($limit < 1 || $limit > 100) {
            throw new \LogicException('Documents requires max_upload_mb between 1 and 100 before activation.');
        }
    }
}
