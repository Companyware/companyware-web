<?php
declare(strict_types=1);
namespace App\Api;
use App\Infrastructure\Database;use App\Infrastructure\Schema;use Companyware\Core\Framework\Application\ActorContextInterface;use Symfony\Component\HttpFoundation\Request;use Symfony\Component\HttpFoundation\Response;use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
final class IdempotencyService
{
    public function __construct(private readonly Database $database,private readonly Schema $schema,private readonly ActorContextInterface $actors){}
    public function execute(Request $request,callable $operation):Response
    {
        $key=trim((string)$request->headers->get('Idempotency-Key'));if($key==='')return $operation();if(strlen($key)>150||!preg_match('/^[A-Za-z0-9._:-]+$/',$key))throw new BadRequestHttpException('Invalid Idempotency-Key.');
        $actor=$this->actors->actorId()??0;if($actor<1)throw new \LogicException('An authenticated actor is required.');$this->schema->ensure();
        $this->database->connection()->exec('DELETE FROM api_idempotency WHERE expires_at<=UTC_TIMESTAMP()');
        $file=$request->files->get('document');$fileHash=$file&&is_file($file->getPathname())?hash_file('sha256',$file->getPathname()):'';
        $hash=hash('sha256',$request->getMethod().' '.$request->getPathInfo().' '.json_encode($request->request->all()).' '.$fileHash);
        $find=$this->database->connection()->prepare('SELECT request_hash,status_code,response_body,response_content_type FROM api_idempotency WHERE admin_user_id=:actor AND idempotency_key=:key AND expires_at>UTC_TIMESTAMP()');$find->execute(['actor'=>$actor,'key'=>$key]);$stored=$find->fetch();
        if(is_array($stored)){if(!hash_equals((string)$stored['request_hash'],$hash))throw new ConflictHttpException('Idempotency-Key was already used for a different request.');$headers=['Idempotency-Replayed'=>'true'];if($stored['response_content_type'])$headers['Content-Type']=(string)$stored['response_content_type'];return new Response((string)$stored['response_body'],(int)$stored['status_code'],$headers);}
        $response=$operation();if($response instanceof Response&&$response->getStatusCode()>=200&&$response->getStatusCode()<300){$insert=$this->database->connection()->prepare('INSERT INTO api_idempotency (admin_user_id,idempotency_key,request_hash,status_code,response_body,response_content_type,expires_at) VALUES (:actor,:key,:hash,:status,:response,:contentType,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))');$insert->execute(['actor'=>$actor,'key'=>$key,'hash'=>$hash,'status'=>$response->getStatusCode(),'response'=>(string)$response->getContent(),'contentType'=>$response->headers->get('Content-Type')]);}
        return $response;
    }
}
