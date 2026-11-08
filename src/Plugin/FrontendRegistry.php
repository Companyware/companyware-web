<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Infrastructure\PluginRegistry;
use Companyware\Core\Framework\Plugin\Contract\FrontendProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FrontendRegistry
{
    /** @param iterable<FrontendProviderInterface> $providers */
    public function __construct(private readonly PluginRegistry $plugins, private readonly iterable $providers)
    {
    }

    public function render(Request $request): ?Response
    {
        $providers = array_filter(
            is_array($this->providers) ? $this->providers : iterator_to_array($this->providers),
            fn (FrontendProviderInterface $provider): bool => $this->plugins->isActive($provider->pluginName()) && $provider->supports($request),
        );
        usort($providers, static fn (FrontendProviderInterface $left, FrontendProviderInterface $right): int => $right->priority() <=> $left->priority() ?: $left->pluginName() <=> $right->pluginName());

        return $providers === [] ? null : $providers[0]->render($request);
    }
}
