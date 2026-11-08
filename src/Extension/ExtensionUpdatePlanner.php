<?php
declare(strict_types=1);
namespace App\Extension;
use App\Infrastructure\PluginRegistry;
use App\Plugin\Compatibility\PluginCompatibilityResolver;
use App\Plugin\PluginManifest;
final class ExtensionUpdatePlanner
{
    public function __construct(private readonly PluginRegistry $plugins,private readonly PluginCompatibilityResolver $compatibility,private readonly PublisherTrustStore $trust){}
    public function plan(array $registry):array
    {
        $installed=$this->plugins->manifests();$byName=[];foreach($installed as $plugin)$byName[$plugin->name]=$plugin;$updates=[];
        foreach($registry['packages'] as $package){if(!is_array($package)||!is_string($package['name']??null)||!isset($byName[$package['name']])||!is_array($package['versions']??null))continue;$current=$byName[$package['name']];$versions=$package['versions'];usort($versions,static fn($a,$b)=>version_compare((string)($b['version']??'0.0.0'),(string)($a['version']??'0.0.0')));$selected=null;$reason='no_compatible_release';foreach($versions as $release){if(!is_array($release)||!is_string($release['version']??null)||version_compare($release['version'],$current->version,'<='))continue;$manifest=PluginManifest::fromArray($release['manifest']??[],'registry://'.$current->name);if(!$manifest||$manifest->name!==$current->name||$manifest->version!==$release['version']){$reason='invalid_manifest';continue;}try{$this->compatibility->assertInstallable($manifest,$installed);}catch(\Throwable){$reason='incompatible';continue;}$signature=$release['signature']??[];$key=is_array($signature)?$this->trust->key((string)($signature['publisher']??''),(string)($signature['keyId']??'')):null;if(!$key||($key['status']??null)!=='trusted'){$reason='untrusted';continue;}$selected=$release+['name'=>$current->name];break;}$advisories=$this->advisories($registry,$current);$updates[$current->name]=['name'=>$current->name,'installed'=>$current->version,'available'=>$selected['version']??null,'release'=>$selected,'reason'=>$selected?'available':$reason,'advisories'=>$advisories];}
        ksort($updates);return array_values($updates);
    }
    private function advisories(array $registry,PluginManifest $plugin):array{$items=[];foreach($registry['advisories']??[] as $advisory){if(!is_array($advisory)||($advisory['package']??null)!==$plugin->name)continue;$affected=(string)($advisory['affected']??'*');try{$match=(new \App\Plugin\Compatibility\VersionConstraint())->matches($plugin->version,$affected);}catch(\Throwable){$match=false;}if($match)$items[]=$advisory;}return $items;}
}
