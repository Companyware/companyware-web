<?php
declare(strict_types=1);
namespace App\Api;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;use Symfony\Component\HttpFoundation\JsonResponse;use Symfony\Component\HttpKernel\Event\ExceptionEvent;use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;use Symfony\Component\HttpKernel\KernelEvents;
final class ApiProblemSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents():array{return [KernelEvents::EXCEPTION=>['problem',100]];}
    public function problem(ExceptionEvent $event):void{if(!str_starts_with($event->getRequest()->getPathInfo(),'/api/v1'))return;$e=$event->getThrowable();$status=$e instanceof HttpExceptionInterface?$e->getStatusCode():500;$title=$status===403?'Forbidden':($status===404?'Not Found':($status===400?'Bad Request':'Internal Server Error'));$event->setResponse(new JsonResponse(['type'=>'https://companyware.dev/problems/'.strtolower(str_replace(' ','-',$title)),'title'=>$title,'status'=>$status,'detail'=>$status<500?$e->getMessage():'An unexpected error occurred.'],$status));}
}
