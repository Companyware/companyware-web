<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use PDO;

final class AccessRepository
{
    public function __construct(private readonly Database $database, private readonly Schema $schema) {}
    public function users(): array { $this->schema->ensure(); return $this->database->connection()->query('SELECT id, username, display_name, email FROM admin_user ORDER BY display_name')->fetchAll(PDO::FETCH_ASSOC); }
    public function roles(): array { $this->schema->ensure(); return $this->database->connection()->query('SELECT * FROM admin_role ORDER BY protected DESC, role_name')->fetchAll(PDO::FETCH_ASSOC); }
    public function privileges(): array { $this->schema->ensure(); return $this->database->connection()->query('SELECT * FROM privilege ORDER BY owner, privilege_group, privilege_key')->fetchAll(PDO::FETCH_ASSOC); }
    public function assignments(): array { $this->schema->ensure(); $rows=$this->database->connection()->query('SELECT admin_user_id, role_id FROM admin_user_role')->fetchAll(PDO::FETCH_ASSOC);$result=[];foreach($rows as $row)$result[(int)$row['admin_user_id']][]=(int)$row['role_id'];return $result; }
    public function rolePrivileges(): array { $this->schema->ensure();$rows=$this->database->connection()->query('SELECT role_id, privilege_key FROM admin_role_privilege')->fetchAll(PDO::FETCH_ASSOC);$result=[];foreach($rows as $row)$result[(int)$row['role_id']][]=$row['privilege_key'];return $result; }
    public function auditLog():array{$this->schema->ensure();return $this->database->connection()->query('SELECT a.*,u.display_name AS actor_name FROM security_audit a LEFT JOIN admin_user u ON u.id=a.actor_admin_id ORDER BY a.created_at DESC,a.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);}
    public function accessSummary(int $adminId): array
    {
        $this->schema->ensure();$db=$this->database->connection();
        $roles=$db->prepare('SELECT r.role_name,r.system_name FROM admin_role r JOIN admin_user_role ur ON ur.role_id=r.id WHERE ur.admin_user_id=:admin ORDER BY r.protected DESC,r.role_name');$roles->execute(['admin'=>$adminId]);$roleRows=$roles->fetchAll(PDO::FETCH_ASSOC);
        $system=(bool)array_filter($roleRows,static fn(array $role):bool=>$role['system_name']==='system-administrator');
        if($system)$privileges=$db->query('SELECT privilege_key,label,owner,privilege_group FROM privilege ORDER BY owner,privilege_group,privilege_key')->fetchAll(PDO::FETCH_ASSOC);
        else{$statement=$db->prepare('SELECT DISTINCT p.privilege_key,p.label,p.owner,p.privilege_group FROM privilege p JOIN admin_role_privilege rp ON rp.privilege_key=p.privilege_key JOIN admin_user_role ur ON ur.role_id=rp.role_id WHERE ur.admin_user_id=:admin ORDER BY p.owner,p.privilege_group,p.privilege_key');$statement->execute(['admin'=>$adminId]);$privileges=$statement->fetchAll(PDO::FETCH_ASSOC);}
        return ['roles'=>$roleRows,'privileges'=>$privileges,'systemAdministrator'=>$system];
    }

    public function createRole(string $name, array $privileges): int
    {
        $name=trim($name);if($name==='')throw new \InvalidArgumentException('security.error.role_name');
        $statement=$this->database->connection()->prepare('INSERT INTO admin_role (role_name) VALUES (:name)');$statement->execute(['name'=>$name]);$id=(int)$this->database->connection()->lastInsertId();$this->setRolePrivileges($id,$privileges);return $id;
    }
    public function setRolePrivileges(int $roleId,array $privileges): void
    {
        $role=$this->role($roleId);if(!$role||$role['protected'])throw new \LogicException('security.error.protected_role');
        $db=$this->database->connection();$db->beginTransaction();try{$db->prepare('DELETE FROM admin_role_privilege WHERE role_id=:id')->execute(['id'=>$roleId]);
        $insert=$db->prepare('INSERT INTO admin_role_privilege (role_id, privilege_key) SELECT :role, privilege_key FROM privilege WHERE privilege_key=:privilege AND protected=0');
        foreach(array_unique(array_filter($privileges,'is_string')) as $privilege)$insert->execute(['role'=>$roleId,'privilege'=>$privilege]);$db->commit();}catch(\Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
    }
    public function setRolePrivilegeMatrix(array $roleIds,array $matrix): void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$roleIds),static fn(int $id):bool=>$id>0)));
        if($ids===[])return;
        $db=$this->database->connection();
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $statement=$db->prepare("SELECT id, protected FROM admin_role WHERE id IN ($placeholders)");$statement->execute($ids);$roles=$statement->fetchAll(PDO::FETCH_KEY_PAIR);
        if(count($roles)!==count($ids)||array_filter($roles,static fn(mixed $protected):bool=>(bool)$protected))throw new \LogicException('security.error.protected_role');
        $db->beginTransaction();
        try{
            $delete=$db->prepare('DELETE FROM admin_role_privilege WHERE role_id=:id');
            $insert=$db->prepare('INSERT INTO admin_role_privilege (role_id, privilege_key) SELECT :role, privilege_key FROM privilege WHERE privilege_key=:privilege AND protected=0');
            foreach($ids as $id){$delete->execute(['id'=>$id]);foreach(array_unique(array_filter($matrix[(string)$id]??$matrix[$id]??[],'is_string')) as $privilege)$insert->execute(['role'=>$id,'privilege'=>$privilege]);}
            $db->commit();
        }catch(\Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
    }
    public function assignRoles(int $adminId,array $roleIds,bool $allowProtected=false): void
    {
        $ids=array_values(array_unique(array_map('intval',$roleIds)));
        $db=$this->database->connection();$system=(int)$db->query("SELECT id FROM admin_role WHERE system_name='system-administrator'")->fetchColumn();
        $check=$db->prepare('SELECT 1 FROM admin_user_role WHERE admin_user_id=:admin AND role_id=:role');$check->execute(['admin'=>$adminId,'role'=>$system]);$wasSystem=(bool)$check->fetchColumn();
        if(!$allowProtected){$ids=array_values(array_diff($ids,[$system]));if($wasSystem)$ids[]=$system;}
        if($ids===[])throw new \LogicException('security.error.role_required');
        if($wasSystem&&!in_array($system,$ids,true)){ $count=(int)$db->query("SELECT COUNT(DISTINCT admin_user_id) FROM admin_user_role WHERE role_id=$system")->fetchColumn();if($count<=1)throw new \LogicException('security.error.last_system_admin'); }
        $db->beginTransaction();try{$db->prepare('DELETE FROM admin_user_role WHERE admin_user_id=:id')->execute(['id'=>$adminId]);$insert=$db->prepare('INSERT INTO admin_user_role (admin_user_id, role_id) SELECT :admin, id FROM admin_role WHERE id=:role');foreach($ids as $id)$insert->execute(['admin'=>$adminId,'role'=>$id]);$verify=$db->prepare('SELECT COUNT(*) FROM admin_user_role WHERE admin_user_id=:id');$verify->execute(['id'=>$adminId]);if((int)$verify->fetchColumn()<1)throw new \LogicException('security.error.role_required');$db->commit();}catch(\Throwable $exception){if($db->inTransaction())$db->rollBack();throw $exception;}
    }
    private function role(int $id): ?array{$statement=$this->database->connection()->prepare('SELECT * FROM admin_role WHERE id=:id');$statement->execute(['id'=>$id]);return $statement->fetch(PDO::FETCH_ASSOC)?:null;}
}
