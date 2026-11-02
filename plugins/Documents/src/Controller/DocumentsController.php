<?php
namespace Plugins\Documents\Controller;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Plugins\Documents\Application\DocumentApplicationService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Symfony\Contracts\Translation\TranslatorInterface;
#[AsController]
final class DocumentsController
{
    #[Route('',name:'documents_index',methods:['GET','POST'])]
    public function index(Request $request,DocumentApplicationService $documents,Environment $twig,TranslatorInterface $translator): Response
    {
        $error=null;
        if($request->isMethod('POST')){
            if(!$this->validToken($request))$error=$translator->trans('documents.error.expired');
            else try{$file=$request->files->get('document');if(!$file)throw new \RuntimeException('documents.error.choose');$documents->add($file,trim((string)$request->request->get('title')));return new RedirectResponse('/admin/documents');}catch(\Throwable $e){$error=str_starts_with($e->getMessage(),'documents.')?$translator->trans($e->getMessage(),['%size%'=>$documents->maxUploadMb()]):$e->getMessage();}
        }
        $token=bin2hex(random_bytes(24));$request->getSession()->set('admin_token',$token);$request->getSession()->set('logout_token',$token);
        return new Response($twig->render('@Documents/admin/index.html.twig',['title'=>'documents.title','admin'=>$request->getSession()->get('admin'),'token'=>$token,'documents'=>$documents->list(),'maxUploadMb'=>$documents->maxUploadMb(),'error'=>$error]));
    }
    #[Route('/{id}/download',name:'documents_download',requirements:['id'=>'\d+'],methods:['GET'])]
    public function download(int $id,DocumentApplicationService $documents,TranslatorInterface $translator): Response
    {
        $document=$documents->find($id);if(!$document||!is_file($documents->path($document)))return new Response($translator->trans('documents.error.not_found'),404);
        $response=new BinaryFileResponse($documents->path($document));$response->headers->set('Content-Type',$document['mime_type']);$response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$document['original_name']);return $response;
    }
    #[Route('/{id}/delete',name:'documents_delete',requirements:['id'=>'\d+'],methods:['POST'])]
    public function delete(int $id,Request $request,DocumentApplicationService $documents): Response { if($this->validToken($request))$documents->delete($id);return new RedirectResponse('/admin/documents'); }
    private function validToken(Request $request): bool{return hash_equals((string)$request->getSession()->get('admin_token'),(string)$request->request->get('_token'));}
}
