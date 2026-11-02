<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use Companyware\Core\Framework\Application\ActorContextInterface;
use Companyware\Core\Framework\Security\AuthorizationInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AuthorizationService implements AuthorizationInterface
{
    /** @var array<string, bool> */
    private array $decisions = [];

    public function __construct(private readonly ActorContextInterface $actors, private readonly Database $database, private readonly Schema $schema) {}

    public function isGranted(string $privilege): bool
    {
        $id = $this->actors->actorId() ?? 0;
        if ($id < 1) return false;
        $cacheKey = $id.':'.$privilege;
        if (array_key_exists($cacheKey, $this->decisions)) return $this->decisions[$cacheKey];
        $this->schema->ensure();
        $statement = $this->database->connection()->prepare("SELECT COUNT(*) FROM admin_user_role ur JOIN admin_role r ON r.id = ur.role_id LEFT JOIN admin_role_privilege rp ON rp.role_id = r.id AND rp.privilege_key = :privilege WHERE ur.admin_user_id = :admin AND (r.system_name = 'system-administrator' OR rp.privilege_key IS NOT NULL)");
        $statement->execute(['admin'=>$id,'privilege'=>$privilege]);

        return $this->decisions[$cacheKey] = (int) $statement->fetchColumn() > 0;
    }

    public function denyUnlessGranted(string $privilege): void
    {
        if (!$this->isGranted($privilege)) throw new AccessDeniedHttpException('Missing privilege: '.$privilege);
    }
}
