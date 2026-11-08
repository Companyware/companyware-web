<?php
declare(strict_types=1);
namespace App\Extension;
final class ExtensionRegistryClient
{
    private const MAX_INDEX=5_000_000;private const MAX_PACKAGE=20_000_000;
    public function registry(?string $location=null):array
    {
        $location=$location?:trim((string)getenv('COMPANYWARE_EXTENSION_REGISTRY'));if($location==='')return ['format'=>'companyware-extension-registry-1','packages'=>[],'advisories'=>[]];
        $contents=$this->read($location,self::MAX_INDEX);$data=json_decode($contents,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data)||($data['format']??null)!=='companyware-extension-registry-1'||!is_array($data['packages']??null)||!is_array($data['advisories']??[]))throw new \RuntimeException('Extension registry document is invalid.');
        return $data+['advisories'=>[]];
    }
    public function download(string $location):string
    {
        $contents=$this->read($location,self::MAX_PACKAGE);$file=tempnam(sys_get_temp_dir(),'cw-extension-');if($file===false)throw new \RuntimeException('Could not allocate a package download file.');file_put_contents($file,$contents);return $file;
    }
    private function read(string $location,int $limit):string
    {
        if(preg_match('#^https://#i',$location)){$context=stream_context_create(['http'=>['timeout'=>20,'follow_location'=>0,'user_agent'=>'Companyware-Extension-Client/1.0'],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);$contents=@file_get_contents($location,false,$context,0,$limit+1);}
        elseif(!str_contains($location,'://')){$path=realpath($location);$contents=$path&&is_file($path)?file_get_contents($path):false;}
        else throw new \RuntimeException('Registry and package URLs must use HTTPS.');
        if(!is_string($contents))throw new \RuntimeException('Registry resource could not be read.');if(strlen($contents)>$limit)throw new \RuntimeException('Registry resource exceeds the size limit.');return $contents;
    }
}
