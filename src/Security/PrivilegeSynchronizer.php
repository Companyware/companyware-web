<?php

declare(strict_types=1);

namespace App\Security;

use App\Infrastructure\Database;
use App\Infrastructure\PluginRegistry;
use App\Infrastructure\Schema;
use App\Plugin\PluginManifest;

final class PrivilegeSynchronizer
{
    public function __construct(private readonly Database $database, private readonly Schema $schema, private readonly PluginRegistry $plugins) {}

    public function sync(): void
    {
        $this->schema->ensure();
        foreach($this->plugins->manifests() as $plugin)$this->syncManifest($plugin);
    }
    public function syncManifest(PluginManifest $plugin):void{$this->schema->ensure();$statement=$this->database->connection()->prepare('INSERT INTO privilege (privilege_key, owner, label, privilege_group, protected, supported_scopes) VALUES (:key,:owner,:label,:group,0,:scopes) ON DUPLICATE KEY UPDATE owner=VALUES(owner),label=VALUES(label),privilege_group=VALUES(privilege_group),supported_scopes=VALUES(supported_scopes)');foreach($plugin->privileges as $privilege)$statement->execute(['key'=>$privilege['key'],'owner'=>$plugin->name,'label'=>$privilege['label'],'group'=>$privilege['group'],'scopes'=>implode(',', $privilege['scopes']??['all'])]);}
    public function remove(string $owner):void{$this->schema->ensure();$statement=$this->database->connection()->prepare('DELETE FROM privilege WHERE owner=:owner AND protected=0');$statement->execute(['owner'=>$owner]);}
}
