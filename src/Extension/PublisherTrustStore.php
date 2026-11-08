<?php
declare(strict_types=1);
namespace App\Extension;
use Symfony\Component\Filesystem\Filesystem;
final class PublisherTrustStore
{
    private string $file;
    public function __construct(string $projectDir){$this->file=$projectDir.'/var/extension-trust.json';}
    public function trust(string $publisher,string $keyId,string $publicKey):void
    {
        $this->assertId($publisher);$this->assertId($keyId);$decoded=base64_decode($publicKey,true);
        if($decoded===false||strlen($decoded)!==32)throw new \InvalidArgumentException('Public key must be a base64-encoded Ed25519 key.');
        $data=$this->read();$data['publishers'][$publisher]['keys'][$keyId]=['algorithm'=>'ed25519','publicKey'=>$publicKey,'status'=>'trusted','updatedAt'=>gmdate(DATE_ATOM)];$this->write($data);
    }
    public function revoke(string $publisher,string $keyId):void
    {$data=$this->read();if(!isset($data['publishers'][$publisher]['keys'][$keyId]))throw new \InvalidArgumentException('Trusted publisher key was not found.');$data['publishers'][$publisher]['keys'][$keyId]['status']='revoked';$data['publishers'][$publisher]['keys'][$keyId]['updatedAt']=gmdate(DATE_ATOM);$this->write($data);}
    public function key(string $publisher,string $keyId):?array{return $this->read()['publishers'][$publisher]['keys'][$keyId]??null;}
    public function all():array{return $this->read();}
    private function read():array{if(!is_file($this->file))return ['format'=>'companyware-trust-1','publishers'=>[]];$data=json_decode((string)file_get_contents($this->file),true);return is_array($data)&&($data['format']??null)==='companyware-trust-1'&&is_array($data['publishers']??null)?$data:['format'=>'companyware-trust-1','publishers'=>[]];}
    private function write(array $data):void{$filesystem=new Filesystem();$filesystem->mkdir(dirname($this->file),0750);$filesystem->dumpFile($this->file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");chmod($this->file,0640);}
    private function assertId(string $id):void{if(!preg_match('/^[a-z0-9][a-z0-9._-]{1,100}$/',$id))throw new \InvalidArgumentException('Publisher and key IDs use lowercase letters, numbers, dots, underscores and hyphens.');}
}
