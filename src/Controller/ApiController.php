<?php
declare(strict_types=1);
namespace App\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\Routing\Attribute\Route;
final class ApiController
{
    #[Route('/api/v1/openapi.json',name:'api_v1_openapi',methods:['GET'])]
    public function openApi():JsonResponse{return new JsonResponse(['openapi'=>'3.1.0','info'=>['title'=>'Companyware API','version'=>'1.0.0'],'servers'=>[['url'=>'/api/v1']],'paths'=>['/documents'=>['get'=>['summary'=>'List documents','security'=>[['bearerAuth'=>[]]],'parameters'=>[['name'=>'limit','in'=>'query','schema'=>['type'=>'integer','minimum'=>1,'maximum'=>100]],['name'=>'offset','in'=>'query','schema'=>['type'=>'integer','minimum'=>0]]],'responses'=>['200'=>['description'=>'Paginated documents'],'401'=>['description'=>'Missing or invalid access token'],'403'=>['description'=>'Missing privilege']]]],'/documents/{id}'=>['get'=>['summary'=>'Read one document','security'=>[['bearerAuth'=>[]]],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Document'],'404'=>['description'=>'Document not found']]]]],'components'=>['securitySchemes'=>['bearerAuth'=>['type'=>'http','scheme'=>'bearer']]]]);}
}
