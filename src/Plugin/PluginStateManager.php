<?php
declare(strict_types=1);
namespace App\Plugin;
use App\Infrastructure\PluginRegistry;
use App\Plugin\Compatibility\PluginCompatibilityResolver;
use App\Plugin\Migration\MigrationManager;
use App\Security\SecurityAuditLogger;
use Companyware\Core\Framework\Plugin\Event\PluginLifecycleOperation;
final class PluginStateManager
{
    public function __construct(private readonly PluginRegistry $registry,private readonly PluginCompatibilityResolver $compatibility,private readonly PluginEventBus $events,private readonly MigrationManager $migrations,private readonly PluginLifecycleManager $lifecycle,private readonly SecurityAuditLogger $audit){}
    public function activate(PluginManifest $plugin):void
    {
        if($this->registry->isActive($plugin->name))return;
        $this->compatibility->assertActivatable($plugin,$this->registry->manifests(),$this->registry->activeManifests());
        $this->events->before($plugin->name,PluginLifecycleOperation::Activate);$this->migrations->migrate($plugin);$this->lifecycle->activate($plugin->name);$this->registry->setActive($plugin->name,true);$this->events->after($plugin->name,PluginLifecycleOperation::Activate);$this->audit->log('plugin.activated','plugin',$plugin->name,['channel'=>'cli_or_admin']);
    }
    public function deactivate(PluginManifest $plugin):void
    {
        if(!$this->registry->isActive($plugin->name))return;
        $this->compatibility->assertDeactivatable($plugin,$this->registry->activeManifests());
        $this->events->before($plugin->name,PluginLifecycleOperation::Deactivate);$this->lifecycle->deactivate($plugin->name);$this->registry->setActive($plugin->name,false);$this->events->after($plugin->name,PluginLifecycleOperation::Deactivate);$this->audit->log('plugin.deactivated','plugin',$plugin->name,['channel'=>'cli_or_admin']);
    }
}
