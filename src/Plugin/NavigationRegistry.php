<?php
namespace App\Plugin;
use App\Infrastructure\PluginRegistry;
use Companyware\Core\Framework\Plugin\Contract\NavigationProviderInterface;
final class NavigationRegistry
{
    /** @param iterable<NavigationProviderInterface> $providers */
    public function __construct(private readonly PluginRegistry $plugins, private readonly iterable $providers) {}
    public function items(): array
    {
        $items = [
            ['section'=>'VERWALTUNG','label'=>'Pluginmanager','route'=>'admin_plugins','icon'=>'◇','position'=>10],
            ['section'=>'KONTO','label'=>'Benutzerprofil','route'=>'admin_profile','icon'=>'○','position'=>900],
            ['section'=>'KONTO','label'=>'Einstellungen','route'=>'admin_settings','icon'=>'⚙','position'=>910],
        ];
        foreach ($this->plugins->activeManifests() as $plugin) foreach ($plugin->navigation as $item) $items[] = $item + ['section'=>'PLUGINS','icon'=>'□','position'=>500];
        foreach ($this->providers as $provider) if ($this->plugins->isActive($provider->pluginName())) foreach ($provider->items() as $item) $items[] = $item;
        usort($items, fn($a,$b)=>($a['position']??500)<=>($b['position']??500));
        return $items;
    }
}
