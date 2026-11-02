<?php
declare(strict_types=1);
namespace Plugins\Documents;
use Companyware\Core\Framework\Api\ApiOperationProviderInterface;
final class DocumentsApiOperationProvider implements ApiOperationProviderInterface
{
    public function paths():array
    {
        $security=[['bearerAuth'=>[]],['oauth2'=>[]]];
        $problem=['401'=>['description'=>'Missing or invalid access token'],'403'=>['description'=>'Missing privilege'],'429'=>['description'=>'Rate limit exceeded']];
        return [
            '/documents'=>[
                'get'=>['summary'=>'List documents','security'=>$security,'parameters'=>[['name'=>'limit','in'=>'query','schema'=>['type'=>'integer','minimum'=>1,'maximum'=>100]],['name'=>'offset','in'=>'query','schema'=>['type'=>'integer','minimum'=>0]],['name'=>'uploaded_by','in'=>'query','schema'=>['type'=>'integer','minimum'=>1]],['name'=>'mime_type','in'=>'query','schema'=>['type'=>'string']],['name'=>'sort','in'=>'query','schema'=>['type'=>'string','enum'=>['created_at','title','updated_at']]],['name'=>'direction','in'=>'query','schema'=>['type'=>'string','enum'=>['asc','desc']]]],'responses'=>['200'=>['description'=>'Paginated documents']]+$problem],
                'post'=>['summary'=>'Create a document','security'=>$security,'parameters'=>[['name'=>'Idempotency-Key','in'=>'header','schema'=>['type'=>'string','maxLength'=>150]]],'requestBody'=>['required'=>true,'content'=>['multipart/form-data'=>['schema'=>['type'=>'object','required'=>['document'],'properties'=>['title'=>['type'=>'string'],'document'=>['type'=>'string','format'=>'binary']]]]]],'responses'=>['201'=>['description'=>'Document created'],'409'=>['description'=>'Idempotency conflict']]+$problem],
            ],
            '/documents/{id}'=>[
                'get'=>['summary'=>'Read one document','security'=>$security,'parameters'=>$this->idParameter(),'responses'=>['200'=>['description'=>'Document'],'404'=>['description'=>'Document not found']]+$problem],
                'delete'=>['summary'=>'Delete a document','security'=>$security,'parameters'=>array_merge($this->idParameter(),[['name'=>'Idempotency-Key','in'=>'header','schema'=>['type'=>'string','maxLength'=>150]]]),'responses'=>['204'=>['description'=>'Document deleted'],'404'=>['description'=>'Document not found'],'409'=>['description'=>'Idempotency conflict']]+$problem],
            ],
            '/documents/{id}/content'=>['get'=>['summary'=>'Download document content','security'=>$security,'parameters'=>$this->idParameter(),'responses'=>['200'=>['description'=>'Document content'],'404'=>['description'=>'Document not found']]+$problem]],
        ];
    }
    private function idParameter():array{return [['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer','minimum'=>1]]];}
}
