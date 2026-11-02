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
        $secret=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$token='cw_pat_'.$secret;
        $insert=$this->database->connection()->prepare('INSERT INTO api_access_token (admin_user_id,token_name,token_hash,expires_at) VALUES (:user,:name,:hash,:expires)');$insert->execute(['user'=>$userId,'name'=>trim($name)?:'API token','hash'=>hash('sha256',$token),'expires'=>$expiresAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);return $token;
    }
    public function resolve(string $token):?int
    {
        if(!str_starts_with($token,'cw_pat_'))return null;$this->schema->ensure();$statement=$this->database->connection()->prepare('SELECT id,admin_user_id FROM api_access_token WHERE token_hash=:hash AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())');$statement->execute(['hash'=>hash('sha256',$token)]);$row=$statement->fetch();if(!is_array($row))return null;$this->database->connection()->prepare('UPDATE api_access_token SET last_used_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id'=>$row['id']]);return (int)$row['admin_user_id'];
    }
}
