<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use Companyware\Core\Framework\Application\ActorContextInterface;
use Companyware\Core\Framework\Security\ScopeContext;
use Companyware\Core\Framework\Security\ScopeContextInterface;

final class DatabaseScopeContext implements ScopeContextInterface
{
    public function __construct(private readonly ActorContextInterface $actors, private readonly Database $database, private readonly Schema $schema) {}

    public function current(): ?ScopeContext
    {
        $id = $this->actors->actorId();
        if (!$id) return null;
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare('SELECT department_key FROM admin_user WHERE id=:id');
        $statement->execute(['id' => $id]);
        $department = $statement->fetchColumn();
        return new ScopeContext($id, is_string($department) && $department !== '' ? $department : null);
    }
}
