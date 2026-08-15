<?php
namespace Plugins\Documents\Controller;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Plugins\Documents\DocumentStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
#[AsController]
final class DocumentsController
{
    #[Route('',name:'documents_index',methods:['GET','POST'])]
    public function index(Request $request,DocumentStorage $storage,Environment $twig): Response
    {
        $error=null;
        if($request->isMethod('POST')){
            if(!$this->validToken($request))$error='Die Sitzung ist abgelaufen.';
            else try{$file=$request->files->get('document');if(!$file)throw new \RuntimeException('Bitte wähle ein Dokument.');$storage->add($file,trim((string)$request->request->get('title')),(int)$request->getSession()->get('admin')['id']);return new RedirectResponse('/admin/documents');}catch(\Throwable $e){$error=$e->getMessage();}
        }
        $token=bin2hex(random_bytes(24));$request->getSession()->set('admin_token',$token);$request->getSession()->set('logout_token',$token);
        return new Response($twig->render('@Documents/admin/index.html.twig',['title'=>'Dokumentenmanagement','admin'=>$request->getSession()->get('admin'),'token'=>$token,'documents'=>$storage->all(),'error'=>$error]));
    }
    #[Route('/{id}/download',name:'documents_download',requirements:['id'=>'\d+'],methods:['GET'])]
    public function download(int $id,DocumentStorage $storage): Response
    {
        $document=$storage->find($id);if(!$document||!is_file($storage->path($document)))return new Response('Dokument nicht gefunden.',404);
        $response=new BinaryFileResponse($storage->path($document));$response->headers->set('Content-Type',$document['mime_type']);$response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$document['original_name']);return $response;
    }
    #[Route('/{id}/delete',name:'documents_delete',requirements:['id'=>'\d+'],methods:['POST'])]
    public function delete(int $id,Request $request,DocumentStorage $storage): Response { if($this->validToken($request))$storage->delete($id);return new RedirectResponse('/admin/documents'); }
    private function validToken(Request $request): bool{return hash_equals((string)$request->getSession()->get('admin_token'),(string)$request->request->get('_token'));}
}
