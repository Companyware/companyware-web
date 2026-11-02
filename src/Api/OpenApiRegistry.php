<?php
declare(strict_types=1);
namespace App\Api;
use Companyware\Core\Framework\Api\ApiOperationProviderInterface;
final class OpenApiRegistry
{
    /** @param iterable<ApiOperationProviderInterface> $providers */
    public function __construct(private readonly iterable $providers){}
    public function document():array
    {
        $paths=['/oauth/token'=>['post'=>['summary'=>'Issue an OAuth2 access token','requestBody'=>['required'=>true,'content'=>['application/x-www-form-urlencoded'=>['schema'=>['type'=>'object','required'=>['grant_type'],'properties'=>['grant_type'=>['type'=>'string','enum'=>['client_credentials']],'scope'=>['type'=>'string']]]]]],'responses'=>['200'=>['description'=>'Access token'],'400'=>['description'=>'Invalid grant request'],'401'=>['description'=>'Invalid client']]]]];
        foreach($this->providers as $provider)foreach($provider->paths() as $path=>$operations){$paths[$path]=array_replace($paths[$path]??[],$operations);}
        ksort($paths);
        return ['openapi'=>'3.1.0','info'=>['title'=>'Companyware API','version'=>'1.0.0'],'servers'=>[['url'=>'/api/v1']],'paths'=>$paths,'components'=>['securitySchemes'=>['bearerAuth'=>['type'=>'http','scheme'=>'bearer'],'oauth2'=>['type'=>'oauth2','flows'=>['clientCredentials'=>['tokenUrl'=>'/api/v1/oauth/token','scopes'=>[]]]]]]];
    }
}
