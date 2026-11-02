<?php
declare(strict_types=1);
namespace Plugins\Documents\Controller;
use Companyware\Core\Framework\Application\PageRequest;use Plugins\Documents\Application\DocumentApplicationService;use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\HttpFoundation\Request;use Symfony\Component\HttpKernel\Attribute\AsController;use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;use Symfony\Component\Routing\Attribute\Route;
#[AsController]
final class DocumentsApiController
{
    #[Route('',name:'api_v1_documents_list',methods:['GET'])]
    public function list(Request $request,DocumentApplicationService $documents):JsonResponse{$page=$documents->search(new PageRequest(max(1,min(100,(int)$request->query->get('limit',50))),max(0,(int)$request->query->get('offset',0))));$items=array_map(static function(array $document):array{unset($document['stored_name']);return $document;},$page->items);return new JsonResponse(['data'=>$items,'meta'=>['total'=>$page->total,'limit'=>$page->limit,'offset'=>$page->offset]]);}
    #[Route('/{id}',name:'api_v1_documents_get',requirements:['id'=>'\d+'],methods:['GET'])]
    public function get(int $id,DocumentApplicationService $documents):JsonResponse{$document=$documents->find($id);if(!$document)throw new NotFoundHttpException('Document not found.');unset($document['stored_name']);return new JsonResponse(['data'=>$document]);}
}
