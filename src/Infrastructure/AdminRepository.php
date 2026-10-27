<?php

namespace App\Infrastructure;

final class AdminRepository
{
    public function __construct(private readonly Database $database, private readonly Schema $schema)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('SELECT * FROM admin_user WHERE username = :username');
        $statement->execute(['username' => $username]);
        return $statement->fetch() ?: null;
    }

    public function save(string $username, string $password, string $displayName, ?string $email = null): void
    {
        $this->schema->ensure();
        $firstAdministrator = (int) $this->database->connection()->query('SELECT COUNT(*) FROM admin_user')->fetchColumn() === 0;
        $statement = $this->database->connection()->prepare('INSERT INTO admin_user (username, password_hash, display_name, email)
            VALUES (:username, :password, :display_name, :email)
            ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), email = VALUES(email)');
        $statement->execute([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'email' => $email,
        ]);
        if ($firstAdministrator) {
            $assign = $this->database->connection()->prepare("INSERT IGNORE INTO admin_user_role (admin_user_id, role_id) SELECT u.id, r.id FROM admin_user u CROSS JOIN admin_role r WHERE u.username = :username AND r.system_name = 'system-administrator'");
            $assign->execute(['username' => $username]);
        }
    }

    public function createUser(string $username,string $password,string $displayName,?string $email=null):int
    {
        $this->schema->ensure();$username=trim($username);$displayName=trim($displayName);
        if($username===''||$displayName===''||strlen($password)<10)throw new \InvalidArgumentException('security.error.user_invalid');
        $statement=$this->database->connection()->prepare('INSERT INTO admin_user (username,password_hash,display_name,email) VALUES (:username,:password,:display_name,:email)');
        $statement->execute(['username'=>$username,'password'=>password_hash($password,PASSWORD_DEFAULT),'display_name'=>$displayName,'email'=>$email]);
        return (int)$this->database->connection()->lastInsertId();
    }

    public function deleteUnassignedUser(int $id):void{$statement=$this->database->connection()->prepare('DELETE u FROM admin_user u LEFT JOIN admin_user_role ur ON ur.admin_user_id=u.id WHERE u.id=:id AND ur.admin_user_id IS NULL');$statement->execute(['id'=>$id]);}

    public function updateProfile(int $id, string $displayName, ?string $email): void
    {
        $statement = $this->database->connection()->prepare('UPDATE admin_user SET display_name = :display_name, email = :email WHERE id = :id');
        $statement->execute(['id' => $id, 'display_name' => $displayName, 'email' => $email]);
    }

    public function preference(int $id, string $key): ?array
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('SELECT preference_value FROM admin_preference WHERE admin_user_id = :id AND preference_key = :key');
        $statement->execute(['id' => $id, 'key' => $key]);
        $value = $statement->fetchColumn();
        if (!is_string($value)) return null;
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function savePreference(int $id, string $key, array $value): void
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('INSERT INTO admin_preference (admin_user_id, preference_key, preference_value)
            VALUES (:id, :key, :value)
            ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)');
        $statement->execute(['id' => $id, 'key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR)]);
    }

    public function deletePreference(int $id, string $key): void
    {
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('DELETE FROM admin_preference WHERE admin_user_id = :id AND preference_key = :key');
        $statement->execute(['id' => $id, 'key' => $key]);
    }
}
