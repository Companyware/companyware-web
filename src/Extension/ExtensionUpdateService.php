<?php
declare(strict_types=1);
namespace App\Extension;
use App\Plugin\PluginPackageInstaller;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class ExtensionUpdateService
{
    public function __construct(private readonly ExtensionRegistryClient $client,private readonly ExtensionUpdatePlanner $planner,private readonly PackageSignatureVerifier $verifier,private readonly PluginPackageInstaller $installer){}
    public function status(?string $registry=null):array{return $this->planner->plan($this->client->registry($registry));}
    public function update(string $name,?string $registry=null):array
    {
        $items=$this->status($registry);$item=null;foreach($items as $candidate)if($candidate['name']===$name){$item=$candidate;break;}
        if(!$item||!is_array($item['release']??null))throw new \RuntimeException('No trusted compatible update is available.');$release=$item['release'];$url=$release['url']??null;if(!is_string($url))throw new \RuntimeException('Update package URL is missing.');
        $file=$this->client->download($url);try{$this->verifier->verify($file,$release);$plugin=$this->installer->update(new UploadedFile($file,basename(parse_url($url,PHP_URL_PATH)?:$name.'.zip'),'application/zip',null,true));return ['name'=>$plugin->name,'version'=>$plugin->version,'active'=>false];}finally{if(is_file($file))unlink($file);}
    }
}
