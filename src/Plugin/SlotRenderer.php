<?php
namespace App\Plugin;
use Companyware\Core\Framework\Plugin\Contract\SlotProviderInterface;
final class SlotRenderer
{
    /** @param iterable<SlotProviderInterface> $providers */
    public function __construct(private readonly iterable $providers, private readonly \App\Infrastructure\PluginRegistry $plugins) {}
    public function render(string $slot, array $context = []): string
    {
        $html = '';
        foreach ($this->providers as $provider) if ($this->plugins->isActive($provider->pluginName()) && $provider->supports($slot)) $html .= $provider->render($slot, $context);
        return $html;
    }
}
