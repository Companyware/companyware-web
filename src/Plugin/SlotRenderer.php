<?php
namespace App\Plugin;
use Companyware\Core\Framework\Plugin\Contract\SlotProviderInterface;
use Companyware\Core\Framework\Security\AuthorizationInterface;
use Companyware\Core\Framework\Security\PrivilegeAwareInterface;
final class SlotRenderer
{
    /** @param iterable<SlotProviderInterface> $providers */
    public function __construct(private readonly iterable $providers, private readonly \App\Infrastructure\PluginRegistry $plugins, private readonly AuthorizationInterface $authorization) {}
    public function render(string $slot, array $context = []): string
    {
        $html = '';
        foreach ($this->providers as $provider) if ($this->plugins->isActive($provider->pluginName()) && (!$provider instanceof PrivilegeAwareInterface || $this->authorization->isGranted($provider->requiredPrivilege($slot))) && $provider->supports($slot)) $html .= $provider->render($slot, $context);
        return $html;
    }
}
