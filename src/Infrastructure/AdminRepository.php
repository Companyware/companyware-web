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
        $statement = $this->database->connection()->prepare('INSERT INTO admin_user (username, password_hash, display_name, email)
            VALUES (:username, :password, :display_name, :email)
            ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), email = VALUES(email)');
        $statement->execute([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'email' => $email,
        ]);
    }

    public function updateProfile(int $id, string $displayName, ?string $email): void
    {
        $statement = $this->database->connection()->prepare('UPDATE admin_user SET display_name = :display_name, email = :email WHERE id = :id');
        $statement->execute(['id' => $id, 'display_name' => $displayName, 'email' => $email]);
    }
}
