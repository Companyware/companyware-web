<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use Symfony\Component\HttpFoundation\RequestStack;

final class SecurityAuditLogger
{
    public function __construct(private readonly Database $database, private readonly Schema $schema, private readonly RequestStack $requests) {}

    public function log(string $action, string $targetType, ?string $targetId = null, array $context = []): void
    {
        $this->schema->ensure();
        $admin = $this->requests->getCurrentRequest()?->getSession()->get('admin');
        $statement = $this->database->connection()->prepare('INSERT INTO security_audit (actor_admin_id, action_name, target_type, target_id, context_json) VALUES (:actor, :action, :type, :target, :context)');
        $statement->execute(['actor'=>is_array($admin)?(int)($admin['id']??0)?:null:null,'action'=>$action,'type'=>$targetType,'target'=>$targetId,'context'=>$context===[]?null:json_encode($context,JSON_THROW_ON_ERROR)]);
    }
}
