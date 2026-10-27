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
use Symfony\Contracts\Translation\TranslatorInterface;
use Companyware\Core\Framework\Security\AuthorizationInterface;
#[AsController]
final class DocumentsController
{
    #[Route('',name:'documents_index',methods:['GET','POST'])]
    public function index(Request $request,DocumentStorage $storage,Environment $twig,TranslatorInterface $translator,AuthorizationInterface $authorization): Response
    {
        $authorization->denyUnlessGranted('documents.read');
        $error=null;
        if($request->isMethod('POST')){
            $authorization->denyUnlessGranted('documents.create');
            if(!$this->validToken($request))$error=$translator->trans('documents.error.expired');
            else try{$file=$request->files->get('document');if(!$file)throw new \RuntimeException('documents.error.choose');$storage->add($file,trim((string)$request->request->get('title')),(int)$request->getSession()->get('admin')['id']);return new RedirectResponse('/admin/documents');}catch(\Throwable $e){$error=str_starts_with($e->getMessage(),'documents.')?$translator->trans($e->getMessage(),['%size%'=>$storage->maxUploadMb()]):$e->getMessage();}
        }
        $token=bin2hex(random_bytes(24));$request->getSession()->set('admin_token',$token);$request->getSession()->set('logout_token',$token);
        return new Response($twig->render('@Documents/admin/index.html.twig',['title'=>'documents.title','admin'=>$request->getSession()->get('admin'),'token'=>$token,'documents'=>$storage->all(),'maxUploadMb'=>$storage->maxUploadMb(),'error'=>$error]));
    }
    #[Route('/{id}/download',name:'documents_download',requirements:['id'=>'\d+'],methods:['GET'])]
    public function download(int $id,DocumentStorage $storage,TranslatorInterface $translator,AuthorizationInterface $authorization): Response
    {
        $authorization->denyUnlessGranted('documents.read');
        $document=$storage->find($id);if(!$document||!is_file($storage->path($document)))return new Response($translator->trans('documents.error.not_found'),404);
        $response=new BinaryFileResponse($storage->path($document));$response->headers->set('Content-Type',$document['mime_type']);$response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$document['original_name']);return $response;
    }
    #[Route('/{id}/delete',name:'documents_delete',requirements:['id'=>'\d+'],methods:['POST'])]
    public function delete(int $id,Request $request,DocumentStorage $storage,AuthorizationInterface $authorization): Response { $authorization->denyUnlessGranted('documents.delete');if($this->validToken($request))$storage->delete($id);return new RedirectResponse('/admin/documents'); }
    private function validToken(Request $request): bool{return hash_equals((string)$request->getSession()->get('admin_token'),(string)$request->request->get('_token'));}
}
