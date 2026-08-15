<?php
namespace App\Plugin;
use App\Infrastructure\PluginRegistry;
final class AssetRegistry
{
    public function __construct(private readonly PluginRegistry $plugins){}
    public function stylesheets(): array{return $this->resolve('styles',[['handle'=>'core-admin','url'=>'/assets/admin.css','priority'=>0],['handle'=>'core-plugin-manager','url'=>'/assets/plugin-manager.css','priority'=>10]]);}
    public function javascripts(): array{return $this->resolve('scripts',[['handle'=>'core-admin-js','url'=>'/assets/admin.js','priority'=>0,'defer'=>true]]);}
    private function resolve(string $type,array $assets): array
    {
        foreach($this->plugins->activeManifests() as $plugin)foreach($plugin->assets[$type]??[] as $asset){if(!is_array($asset)||!is_string($asset['handle']??null)||!is_string($asset['path']??null))continue;$assets[]=$asset+['priority'=>100,'defer'=>true,'url'=>'/plugin-assets/'.rawurlencode($plugin->name).'?path='.rawurlencode($asset['path'])];}
        $replaced=[];foreach($assets as $asset)foreach((array)($asset['replaces']??[]) as $handle)if(is_string($handle))$replaced[$handle]=true;
        $assets=array_values(array_filter($assets,fn($asset)=>!isset($replaced[$asset['handle']])));usort($assets,fn($a,$b)=>($a['priority']??100)<=>($b['priority']??100));return $assets;
    }
}
