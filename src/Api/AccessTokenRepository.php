<?php
declare(strict_types=1);
namespace App\Api;
use App\Infrastructure\Database;
use App\Infrastructure\Schema;
final class AccessTokenRepository
{
    public function __construct(private readonly Database $database,private readonly Schema $schema){}
    public function issue(string $username,string $name,?\DateTimeImmutable $expiresAt):string
    {
        $this->schema->ensure();$find=$this->database->connection()->prepare('SELECT id FROM admin_user WHERE username=:username');$find->execute(['username'=>$username]);$userId=(int)$find->fetchColumn();if($userId<1)throw new \InvalidArgumentException('Administrator not found.');
        return $this->issueForActor($userId,$name,$expiresAt,'cw_pat_');
    }
    public function issueForActor(int $userId,string $name,?\DateTimeImmutable $expiresAt,string $prefix='cw_pat_'):string
    {
        $this->schema->ensure();if($userId<1)throw new \InvalidArgumentException('Administrator not found.');
        $secret=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$token=$prefix.$secret;
        $insert=$this->database->connection()->prepare('INSERT INTO api_access_token (admin_user_id,token_name,token_hash,expires_at) VALUES (:user,:name,:hash,:expires)');$insert->execute(['user'=>$userId,'name'=>trim($name)?:'API token','hash'=>hash('sha256',$token),'expires'=>$expiresAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);return $token;
    }
    public function resolve(string $token):?int
    {
        if(!str_starts_with($token,'cw_pat_'))return null;$this->schema->ensure();$statement=$this->database->connection()->prepare('SELECT id,admin_user_id FROM api_access_token WHERE token_hash=:hash AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())');$statement->execute(['hash'=>hash('sha256',$token)]);$row=$statement->fetch();if(!is_array($row))return null;$this->database->connection()->prepare('UPDATE api_access_token SET last_used_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id'=>$row['id']]);return (int)$row['admin_user_id'];
    }
    public function allowRequest(string $token,int $limit=120):bool
    {
        $this->schema->ensure();$bucket=hash('sha256',$token);$window=gmdate('Y-m-d H:i:00');$statement=$this->database->connection()->prepare('INSERT INTO api_rate_limit (bucket_key,window_start,request_count) VALUES (:bucket,:window,1) ON DUPLICATE KEY UPDATE request_count=request_count+1');$statement->execute(['bucket'=>$bucket,'window'=>$window]);$read=$this->database->connection()->prepare('SELECT request_count FROM api_rate_limit WHERE bucket_key=:bucket AND window_start=:window');$read->execute(['bucket'=>$bucket,'window'=>$window]);return (int)$read->fetchColumn()<=$limit;
    }
    public function revoke(int $id):void{$this->schema->ensure();$statement=$this->database->connection()->prepare('UPDATE api_access_token SET revoked_at=UTC_TIMESTAMP() WHERE id=:id AND revoked_at IS NULL');$statement->execute(['id'=>$id]);if($statement->rowCount()!==1)throw new \InvalidArgumentException('Active token not found.');}
    public function list(string $username):array{$this->schema->ensure();$statement=$this->database->connection()->prepare('SELECT t.id,t.token_name,t.expires_at,t.last_used_at,t.revoked_at,t.created_at FROM api_access_token t JOIN admin_user u ON u.id=t.admin_user_id WHERE u.username=:username ORDER BY t.created_at DESC');$statement->execute(['username'=>$username]);return $statement->fetchAll();}
}
