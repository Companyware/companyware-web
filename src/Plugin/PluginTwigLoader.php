<?php
namespace App\Plugin;
use App\Infrastructure\PluginRegistry;
use Twig\Loader\FilesystemLoader;
final class PluginTwigLoader
{
    private bool $loaded=false;
    public function __construct(private readonly FilesystemLoader $loader, private readonly PluginRegistry $plugins) {}
    public function load(): void
    {
        if ($this->loaded) return;
        foreach ($this->plugins->activeManifests() as $plugin) {
            $templates=$plugin->path.'/templates'; if (!is_dir($templates)) continue;
            $this->loader->addPath($templates, $plugin->namespace());
            if (is_dir($templates.'/overrides')) $this->loader->prependPath($templates.'/overrides');
        }
        $this->loaded=true;
    }
}
