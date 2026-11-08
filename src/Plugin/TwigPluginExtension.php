<?php
namespace App\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Companyware\Core\Framework\Security\AuthorizationInterface;
final class TwigPluginExtension extends AbstractExtension
{
    public function __construct(private readonly SlotRenderer $slots, private readonly NavigationRegistry $navigation, private readonly AssetRegistry $assets, private readonly AuthorizationInterface $authorization, private readonly \Companyware\Core\Framework\Theme\ThemeRegistryInterface $themes) {}
    public function getFunctions(): array
    {
        return [
            new TwigFunction('plugin_slot', fn(string $name,array $context=[])=>$this->slots->render($name,$context), ['is_safe'=>['html']]),
            new TwigFunction('admin_navigation', fn()=>$this->navigation->items()),
            new TwigFunction('plugin_asset', fn(string $plugin,string $path)=>'/plugin-assets/'.rawurlencode($plugin).'?path='.rawurlencode(ltrim($path,'/'))),
            new TwigFunction('admin_stylesheets', fn()=>$this->assets->stylesheets()),
            new TwigFunction('admin_javascripts', fn()=>$this->assets->javascripts()),
            new TwigFunction('is_granted', fn(string $privilege)=>$this->authorization->isGranted($privilege)),
            new TwigFunction('theme_stylesheets', fn()=> $this->themes->stylesheets()),
            new TwigFunction('active_theme', fn()=> $this->themes->selected()),
        ];
    }
}
