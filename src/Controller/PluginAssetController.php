<?php
namespace App\Controller;
use App\Infrastructure\PluginRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class PluginAssetController
{
    #[Route('/plugin-assets/{plugin}', name:'plugin_asset', methods:['GET'])]
    public function __invoke(string $plugin, \Symfony\Component\HttpFoundation\Request $request, PluginRegistry $plugins): Response
    {
        $path=(string)$request->query->get('path');
        foreach ($plugins->activeManifests() as $manifest) if ($manifest->name === $plugin) {
            $root=realpath($manifest->path.'/public'); $file=realpath($manifest->path.'/public/'.$path);
            if ($root && $file && str_starts_with($file,$root.DIRECTORY_SEPARATOR) && is_file($file)) return new BinaryFileResponse($file);
        }
        return new Response('Asset not found.',404);
    }
}
