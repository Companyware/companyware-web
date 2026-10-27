<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\AccessRepository;
use App\Security\PrivilegeSynchronizer;
use App\Security\SecurityAuditLogger;
use App\Infrastructure\AdminRepository;
use Companyware\Core\Framework\Security\RequiresPrivilege;
use Companyware\Core\Framework\Security\AuthorizationInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[RequiresPrivilege('core.security.manage')]
final class AccessController
{
    #[Route('/admin/access',name:'admin_access',methods:['GET','POST'])]
    #[Route('/admin/users',name:'admin_users',methods:['GET','POST'],defaults:['_access_section'=>'users'])]
    public function index(Request $request,AccessRepository $access,AdminRepository $admins,PrivilegeSynchronizer $privileges,SecurityAuditLogger $audit,Environment $view,TranslatorInterface $translator,AuthorizationInterface $authorization):Response
    {
        $privileges->sync();$error=null;$message=null;
        if($request->isMethod('POST')){
            if(!$this->validToken($request))$error=$translator->trans('security.error.expired');
            else try{
                $action=(string)$request->request->get('action');
                if($action==='create_user'){$id=$admins->createUser((string)$request->request->get('username'),(string)$request->request->get('password'),(string)$request->request->get('display_name'),trim((string)$request->request->get('email'))?:null);try{$access->assignRoles($id,$request->request->all('roles'),$authorization->isGranted('core.recovery'));}catch(\Throwable $exception){$admins->deleteUnassignedUser($id);throw $exception;}$audit->log('security.user.created','admin_user',(string)$id);}
                elseif($action==='create_role'){$id=$access->createRole((string)$request->request->get('role_name'),$request->request->all('privileges'));$audit->log('security.role.created','role',(string)$id);}
                elseif($action==='update_role'){$id=(int)$request->request->get('role_id');$access->setRolePrivileges($id,$request->request->all('privileges'));$audit->log('security.role.privileges_changed','role',(string)$id);}
                elseif($action==='update_role_matrix'){$ids=$request->request->all('role_ids');$access->setRolePrivilegeMatrix($ids,$request->request->all('privileges'));$audit->log('security.role.matrix_changed','role_matrix',implode(',',array_map('intval',$ids)));}
                elseif($action==='assign_roles'){$id=(int)$request->request->get('admin_id');$access->assignRoles($id,$request->request->all('roles'),$authorization->isGranted('core.recovery'));$audit->log('security.user.roles_changed','admin_user',(string)$id);}
                $request->getSession()->set('access_message',$translator->trans('security.saved'));return new RedirectResponse($request->getPathInfo());
            }catch(\Throwable $exception){$error=str_starts_with($exception->getMessage(),'security.')?$translator->trans($exception->getMessage()):$exception->getMessage();}
        }
        $token=bin2hex(random_bytes(24));$request->getSession()->set('admin_token',$token);$request->getSession()->set('logout_token',$token);
        $section=(string)$request->attributes->get('_access_section','roles');
        return new Response($view->render('admin/access/index.html.twig',['title'=>$section==='users'?'security.users_title':'security.title','section'=>$section,'active'=>'access','admin'=>$request->getSession()->get('admin'),'token'=>$token,'users'=>$access->users(),'roles'=>$access->roles(),'privileges'=>$access->privileges(),'assignments'=>$access->assignments(),'rolePrivileges'=>$access->rolePrivileges(),'auditLog'=>$access->auditLog(),'message'=>$request->getSession()->remove('access_message'),'error'=>$error]));
    }
    private function validToken(Request $request):bool{return hash_equals((string)$request->getSession()->get('admin_token'),(string)$request->request->get('_token'));}
}
