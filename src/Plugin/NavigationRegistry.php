<?php
namespace App\Plugin;
use App\Infrastructure\PluginRegistry;
use Companyware\Core\Framework\Plugin\Contract\NavigationProviderInterface;
use Companyware\Core\Framework\Security\AuthorizationInterface;
final class NavigationRegistry
{
    /** @param iterable<NavigationProviderInterface> $providers */
    public function __construct(private readonly PluginRegistry $plugins, private readonly iterable $providers, private readonly AuthorizationInterface $authorization) {}
    public function items(): array
    {
        $items = [
            ['group'=>'work','section'=>'nav.section.workspaces','label'=>'nav.dashboard','route'=>'admin_dashboard','icon'=>'⌂','position'=>0],
            ['group'=>'management','section'=>'nav.section.management','label'=>'nav.plugins','route'=>'admin_plugins','icon'=>'◇','position'=>900,'privilege'=>'core.plugins.manage'],
            ['group'=>'management','section'=>'nav.section.management','label'=>'nav.settings','route'=>'admin_settings','icon'=>'⚙','position'=>910,'privilege'=>'core.settings.manage'],
            ['group'=>'management','section'=>'nav.section.management','label'=>'security.users_nav','route'=>'admin_users','icon'=>'◎','position'=>915,'privilege'=>'core.security.manage'],
            ['group'=>'management','section'=>'nav.section.management','label'=>'security.nav','route'=>'admin_access','icon'=>'⌾','position'=>916,'privilege'=>'core.security.manage'],
            ['group'=>'account','section'=>'nav.section.account','label'=>'nav.profile','route'=>'admin_profile','icon'=>'○','position'=>920,'privilege'=>'core.profile.edit'],
        ];
        foreach ($this->plugins->activeManifests() as $plugin) foreach ($plugin->navigation as $item) $items[] = ['group'=>'work'] + $item + ['section'=>'nav.section.plugins','icon'=>'□','position'=>500];
        foreach ($this->providers as $provider) if ($this->plugins->isActive($provider->pluginName())) foreach ($provider->items() as $item) $items[] = ['group'=>'work'] + $item;
        $items=array_values(array_filter($items,fn(array $item):bool=>!isset($item['privilege'])||$this->authorization->isGranted((string)$item['privilege'])));
        usort($items, fn($a,$b)=>($a['position']??500)<=>($b['position']??500));
        return $items;
    }
}
