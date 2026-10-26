<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Plugin\PluginPackageLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PluginTranslationPass implements CompilerPassInterface
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $translator = $container->getDefinition('translator.default');
        $resources = [];

        foreach ((new PluginPackageLocator($this->projectDir))->directories() as $directory) {
            foreach (glob($directory.'/translations/messages.*.yaml') ?: [] as $file) {
                if (!preg_match('/messages\.([a-z]{2}(?:_[A-Z]{2})?)\.yaml$/', $file, $matches)) {
                    continue;
                }

                $resources[$file] = ['yaml', $file, $matches[1], 'messages'];
            }
        }

        ksort($resources);
        foreach ($resources as $arguments) {
            $translator->addMethodCall('addResource', $arguments);
        }
    }
}
