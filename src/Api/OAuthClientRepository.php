<?php
declare(strict_types=1);
namespace App\Api;
use App\Infrastructure\Database;use App\Infrastructure\Schema;
final class OAuthClientRepository
{
    public function __construct(private readonly Database $database,private readonly Schema $schema){}
    public function create(string $username,string $name):array
    {
        $this->schema->ensure();$find=$this->database->connection()->prepare('SELECT id FROM admin_user WHERE username=:username');$find->execute(['username'=>$username]);$actor=(int)$find->fetchColumn();if($actor<1)throw new \InvalidArgumentException('Administrator not found.');
        $identifier='cw_client_'.bin2hex(random_bytes(12));$secret=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $insert=$this->database->connection()->prepare('INSERT INTO api_oauth_client (client_identifier,client_name,client_secret_hash,admin_user_id) VALUES (:identifier,:name,:hash,:actor)');$insert->execute(['identifier'=>$identifier,'name'=>trim($name)?:'OAuth client','hash'=>hash('sha256',$secret),'actor'=>$actor]);
        return ['client_id'=>$identifier,'client_secret'=>$secret];
    }
    public function authenticate(string $identifier,string $secret):?array
    {
        $this->schema->ensure();$statement=$this->database->connection()->prepare('SELECT id,client_name,client_secret_hash,admin_user_id FROM api_oauth_client WHERE client_identifier=:identifier AND revoked_at IS NULL');$statement->execute(['identifier'=>$identifier]);$row=$statement->fetch();if(!is_array($row)||!hash_equals((string)$row['client_secret_hash'],hash('sha256',$secret)))return null;return $row;
    }
    public function list(string $username):array{$this->schema->ensure();$statement=$this->database->connection()->prepare('SELECT c.id,c.client_identifier,c.client_name,c.revoked_at,c.created_at FROM api_oauth_client c JOIN admin_user u ON u.id=c.admin_user_id WHERE u.username=:username ORDER BY c.created_at DESC');$statement->execute(['username'=>$username]);return $statement->fetchAll();}
    public function revoke(int $id):void{$this->schema->ensure();$statement=$this->database->connection()->prepare('UPDATE api_oauth_client SET revoked_at=UTC_TIMESTAMP() WHERE id=:id AND revoked_at IS NULL');$statement->execute(['id'=>$id]);if($statement->rowCount()!==1)throw new \InvalidArgumentException('Active OAuth client not found.');}
}
