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
            ['group'=>'management','section'=>'nav.section.management','label'=>'nav.plugins','route'=>'admin_plugins','icon'=>'◇','position'=>900],
            ['group'=>'management','section'=>'nav.section.management','label'=>'nav.settings','route'=>'admin_settings','icon'=>'⚙','position'=>910],
            ['group'=>'account','section'=>'nav.section.account','label'=>'nav.profile','route'=>'admin_profile','icon'=>'○','position'=>920],
        ];
        foreach ($this->plugins->activeManifests() as $plugin) foreach ($plugin->navigation as $item) $items[] = ['group'=>'work'] + $item + ['section'=>'nav.section.plugins','icon'=>'□','position'=>500];
        foreach ($this->providers as $provider) if ($this->plugins->isActive($provider->pluginName())) foreach ($provider->items() as $item) $items[] = ['group'=>'work'] + $item;
        usort($items, fn($a,$b)=>($a['position']??500)<=>($b['position']??500));
        return $items;
    }
}
