<?php
declare(strict_types=1);
namespace App\Controller;
use App\Api\AccessTokenRepository;use App\Api\OAuthClientRepository;use App\Api\OpenApiRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\HttpFoundation\Request;use Symfony\Component\Routing\Attribute\Route;
final class ApiController
{
    #[Route('/api/v1/openapi.json',name:'api_v1_openapi',methods:['GET'])]
    public function openApi(OpenApiRegistry $registry):JsonResponse{return new JsonResponse($registry->document());}
    #[Route('/api/v1/oauth/token',name:'api_v1_oauth_token',methods:['POST'])]
    public function token(Request $request,OAuthClientRepository $clients,AccessTokenRepository $tokens):JsonResponse
    {
        if($request->request->get('grant_type')!=='client_credentials')return new JsonResponse(['error'=>'unsupported_grant_type'],400);
        if(trim((string)$request->request->get('scope'))!=='')return new JsonResponse(['error'=>'invalid_scope'],400);
        $identifier=(string)$request->request->get('client_id');$secret=(string)$request->request->get('client_secret');
        if(str_starts_with((string)$request->headers->get('Authorization'),'Basic ')){$decoded=base64_decode(substr((string)$request->headers->get('Authorization'),6),true);if(is_string($decoded)&&str_contains($decoded,':'))[$identifier,$secret]=explode(':',$decoded,2);}
        $client=$clients->authenticate($identifier,$secret);if(!$client)return new JsonResponse(['error'=>'invalid_client'],401,['WWW-Authenticate'=>'Basic realm="Companyware OAuth2"']);
        $expires=new \DateTimeImmutable('+1 hour');$token=$tokens->issueForActor((int)$client['admin_user_id'],'OAuth2: '.$client['client_name'],$expires,'cw_oauth_');
        return new JsonResponse(['access_token'=>$token,'token_type'=>'Bearer','expires_in'=>3600,'scope'=>''],200,['Cache-Control'=>'no-store','Pragma'=>'no-cache']);
    }
}
