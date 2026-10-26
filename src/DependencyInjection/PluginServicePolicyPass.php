<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Plugin\PluginPackageLocator;
use Companyware\Core\Framework\File\FileNameNormalizerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

final class PluginServicePolicyPass implements CompilerPassInterface
{
    private const DECORATABLE_SERVICES = [
        FileNameNormalizerInterface::class,
    ];

    public function __construct(private readonly string $projectDir)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $declaredPluginServices = $this->declaredPluginServices();
        foreach ($container->getDefinitions() as $id => $definition) {
            $decorated = $definition->getDecoratedService();
            if ($decorated === null || !$this->isPluginService($id, $definition->getClass(), $declaredPluginServices)) {
                continue;
            }
            if (!in_array($decorated[0], self::DECORATABLE_SERVICES, true)) {
                throw new \LogicException(sprintf('Plugin service "%s" cannot decorate protected or unpublished service "%s".', $id, $decorated[0]));
            }
        }
    }

    private function isPluginService(string $id, ?string $class, array $declared): bool
    {
        return isset($declared[$id]) || str_starts_with($id, 'Plugins\\') || ($class !== null && str_starts_with($class, 'Plugins\\'));
    }

    private function declaredPluginServices(): array
    {
        $services = [];
        foreach ((new PluginPackageLocator($this->projectDir))->directories() as $directory) {
            $file = $directory.'/config/services.yaml';
            if (!is_file($file)) continue;
            $config = Yaml::parseFile($file);
            foreach (array_keys(is_array($config['services'] ?? null) ? $config['services'] : []) as $id) {
                if (is_string($id) && !str_starts_with($id, '_')) $services[rtrim($id, ':')] = true;
            }
        }

        return $services;
    }

}
