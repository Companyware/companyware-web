<?php
namespace App\Plugin;
use Psr\Container\ContainerInterface;
final class PluginLifecycleManager
{
    public function __construct(private readonly ContainerInterface $plugins) {}
    public function activate(string $name): void { if ($this->plugins->has($name)) $this->plugins->get($name)->activate(); }
    public function deactivate(string $name): void { if ($this->plugins->has($name)) $this->plugins->get($name)->deactivate(); }
}
