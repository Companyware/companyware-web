<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Plugin\PluginPackageLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Resource\DirectoryResource;

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
        $options = $translator->getArgument(4);
        $resources = $options['resource_files'] ?? [];
        $directories = $options['scanned_directories'] ?? [];

        foreach ((new PluginPackageLocator($this->projectDir))->directories() as $directory) {
            $translationDirectory = $directory.'/translations';
            if (!is_dir($translationDirectory)) {
                continue;
            }

            $directories[] = $translationDirectory;
            $container->addResource(new DirectoryResource($translationDirectory));

            foreach (glob($translationDirectory.'/messages.*.yaml') ?: [] as $file) {
                if (!preg_match('/messages\.([a-z]{2}(?:_[A-Z]{2})?)\.yaml$/', $file, $matches)) {
                    continue;
                }

                $resources[$matches[1]][] = $file;
            }
        }

        foreach ($resources as &$files) {
            $files = array_values(array_unique($files));
            sort($files);
        }
        unset($files);

        $directories = array_values(array_unique($directories));
        sort($directories);
        ksort($resources);

        $options['resource_files'] = $resources;
        $options['scanned_directories'] = $directories;
        $options['cache_vary']['scanned_directories'] = array_map(
            fn (string $directory): string => str_starts_with($directory, $this->projectDir.'/')
                ? substr($directory, 1 + strlen($this->projectDir))
                : $directory,
            $directories,
        );
        $translator->replaceArgument(4, $options);
    }
}
