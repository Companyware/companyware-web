<?php
declare(strict_types=1);
namespace App\Extension;
final class PackageSignatureVerifier
{
    public function __construct(private readonly PublisherTrustStore $trust){}
    public function verify(string $file,array $release):void
    {
        if(!function_exists('sodium_crypto_sign_verify_detached'))throw new \RuntimeException('Ed25519 verification requires the PHP sodium extension.');
        if(!is_file($file))throw new \RuntimeException('Downloaded package is missing.');
        $name=$release['name']??null;$version=$release['version']??null;$expected=$release['sha256']??null;$signature=$release['signature']??null;
        if(!is_string($name)||!is_string($version)||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/',$expected)||!is_array($signature))throw new \RuntimeException('Registry release metadata is incomplete.');
        $actual=hash_file('sha256',$file);if(!hash_equals($expected,$actual))throw new \RuntimeException('Package checksum verification failed.');
        $publisher=$signature['publisher']??null;$keyId=$signature['keyId']??null;$value=$signature['value']??null;
        if(!is_string($publisher)||!is_string($keyId)||!is_string($value))throw new \RuntimeException('Package signature metadata is incomplete.');
        $key=$this->trust->key($publisher,$keyId);if(!$key||($key['status']??null)!=='trusted')throw new \RuntimeException('Package publisher key is not trusted or has been revoked.');
        $public=base64_decode((string)$key['publicKey'],true);$signed=base64_decode($value,true);if($public===false||$signed===false)throw new \RuntimeException('Package signature encoding is invalid.');
        $payload=self::payload($name,$version,$expected);if(!sodium_crypto_sign_verify_detached($signed,$payload,$public))throw new \RuntimeException('Package signature verification failed.');
    }
    public static function payload(string $name,string $version,string $sha256):string{return "companyware-package-v1\n$name\n$version\n$sha256";}
    public static function available():bool{return function_exists('sodium_crypto_sign_verify_detached');}
}
