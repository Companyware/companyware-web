<?php
namespace App\Plugin;
use Companyware\Core\Framework\Plugin\Contract\PluginLifecycleInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
final class PluginLifecycleManager
{
    public function __construct(private readonly ContainerInterface $plugins) {}
    public function activate(string $name): void { $this->plugin($name)?->activate(); }
    public function deactivate(string $name): void { $this->plugin($name)?->deactivate(); }
    public function uninstall(string $name): void { $this->plugin($name)?->uninstall(); }

    private function plugin(string $name): ?PluginLifecycleInterface
    {
        if (!$this->plugins->has($name)) return null;
        $plugin = $this->plugins->get($name);
        if (!$plugin instanceof PluginLifecycleInterface) throw new RuntimeException(sprintf('Lifecycle service for "%s" must implement %s.', $name, PluginLifecycleInterface::class));
        return $plugin;
    }
}
