<?php

declare(strict_types=1);

namespace App\Plugin;

use Companyware\Core\Framework\Plugin\Event\AfterPluginLifecycleEvent;
use Companyware\Core\Framework\Plugin\Event\BeforePluginLifecycleEvent;
use Companyware\Core\Framework\Plugin\Event\PluginConfigurationChangedEvent;
use Companyware\Core\Framework\Plugin\Event\PluginLifecycleOperation;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PluginEventBus
{
    public function __construct(private readonly EventDispatcherInterface $events)
    {
    }

    public function before(string $pluginName, PluginLifecycleOperation $operation): void
    {
        $this->events->dispatch(new BeforePluginLifecycleEvent($pluginName, $operation));
    }

    public function after(string $pluginName, PluginLifecycleOperation $operation): void
    {
        $this->postCommit(new AfterPluginLifecycleEvent($pluginName, $operation));
    }

    /** @param list<string> $changedKeys */
    public function configurationChanged(string $pluginName, array $changedKeys): void
    {
        $this->postCommit(new PluginConfigurationChangedEvent($pluginName, $changedKeys));
    }

    private function postCommit(object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (\Throwable $exception) {
            error_log(sprintf('Post-commit plugin event listener failed for %s: %s', $event::class, $exception->getMessage()));
        }
    }
}
