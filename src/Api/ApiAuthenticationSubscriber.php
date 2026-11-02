<?php
declare(strict_types=1);
namespace App\Api;
use App\Application\RequestActorContext;use Symfony\Component\EventDispatcher\EventSubscriberInterface;use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\HttpKernel\Event\RequestEvent;use Symfony\Component\HttpKernel\KernelEvents;
final class ApiAuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AccessTokenRepository $tokens){}
    public static function getSubscribedEvents():array{return [KernelEvents::REQUEST=>['authenticate',100]];}
    public function authenticate(RequestEvent $event):void{$request=$event->getRequest();if(!str_starts_with($request->getPathInfo(),'/api/v1')||$request->getPathInfo()==='/api/v1/openapi.json')return;$header=(string)$request->headers->get('Authorization');if(!preg_match('/^Bearer\s+(.+)$/i',$header,$match)||!($actor=$this->tokens->resolve(trim($match[1])))){$event->setResponse(new JsonResponse(['type'=>'https://companyware.dev/problems/unauthorized','title'=>'Unauthorized','status'=>401],401,['WWW-Authenticate'=>'Bearer']));return;}$request->attributes->set(RequestActorContext::ATTRIBUTE,$actor);}
}
