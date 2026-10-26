<?php

namespace App;

use App\DependencyInjection\PluginTranslationPass;
use App\Plugin\PluginClassLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        PluginClassLoader::register(dirname(__DIR__).'/plugins');
        parent::__construct($environment, $debug);
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PluginTranslationPass(dirname(__DIR__)));
    }
}
