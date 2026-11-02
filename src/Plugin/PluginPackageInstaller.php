<?php
namespace App\Plugin;
use App\Infrastructure\Database;
use App\Infrastructure\PluginRegistry;
use App\Plugin\Compatibility\PluginCompatibilityResolver;
use Companyware\Core\Framework\Plugin\Event\PluginLifecycleOperation;
use App\Plugin\Migration\MigrationManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;
use App\Security\PrivilegeSynchronizer;
final class PluginPackageInstaller
{
    private const MAX_ARCHIVE=20_000_000; private const MAX_EXPANDED=100_000_000; private const MAX_FILES=1000;
    private readonly string $pluginRoot;
    public function __construct(private readonly string $projectDir, private readonly string $cacheDir, private readonly MigrationManager $migrations, private readonly Database $database, private readonly PluginLifecycleManager $lifecycle, private readonly PluginPackageValidator $validator, private readonly PluginCompatibilityResolver $compatibility, private readonly PluginRegistry $registry, private readonly PluginConfiguration $configuration, private readonly PluginEventBus $events, private readonly PrivilegeSynchronizer $privileges)
    { $this->pluginRoot=$projectDir.'/plugins'; }
    public function install(UploadedFile $upload): PluginManifest
    {
        if (!$upload->isValid() || $upload->getSize()>self::MAX_ARCHIVE || strtolower($upload->getClientOriginalExtension())!=='zip') throw new PluginPackageException('plugins.error.valid_zip');
        $zip=new ZipArchive(); if ($zip->open($upload->getPathname())!==true) throw new PluginPackageException('plugins.error.unreadable_zip');
        try {
            if ($zip->numFiles<1 || $zip->numFiles>self::MAX_FILES) throw new PluginPackageException('plugins.error.archive_count');
            $prefix=$this->packagePrefix($zip); $manifestName=$prefix.'plugin.json'; $json=$zip->getFromName($manifestName);
            if (!is_string($json)) throw new PluginPackageException('plugins.error.manifest_missing');
            $data=json_decode($json,true); if (!is_array($data) || !preg_match('/^[a-z][a-z0-9-]+$/',$data['name']??'') || !is_string($data['label']??null) || !preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',$data['version']??'')) throw new PluginPackageException('plugins.error.manifest_invalid');
            $directory=str_replace(' ','',ucwords(str_replace('-',' ',$data['name']))); $target=$this->pluginRoot.'/'.$directory;
            if (file_exists($target)) throw new PluginPackageException('plugins.error.already_installed');
            $candidate=PluginManifest::fromArray($data,$target); if(!$candidate) throw new PluginPackageException('plugins.error.manifest_invalid');
            $this->validator->validateCompatibility($candidate);
            $this->compatibility->assertInstallable($candidate,$this->registry->manifests());
            $stage=$this->pluginRoot.'/.install-'.bin2hex(random_bytes(8)); (new Filesystem())->mkdir($stage,0750);
            try {
                $this->extract($zip,$prefix,$stage);
                $manifest=$this->validator->validate($stage);
                $this->events->before($manifest->name,PluginLifecycleOperation::Install);
                if(!rename($stage,$target))throw new PluginPackageException('plugins.error.atomic_install');
                $manifest=$this->validator->validate($target);
                $this->migrations->migrate($manifest);
                $this->lifecycle->install($manifest->name);
                $this->privileges->syncManifest($manifest);
                $this->events->after($manifest->name,PluginLifecycleOperation::Install);
                $this->invalidateContainer();
                return $manifest;
            }
            catch (\Throwable $e) {
                $filesystem=new Filesystem();
                $filesystem->remove($stage);
                if(is_dir($target)) {
                    try { if (isset($manifest)) $this->migrations->rollback($manifest, true); }
                    catch (\Throwable $rollbackError) { throw new PluginPackageException('plugins.error.install_recovery', 0, $rollbackError); }
                    $filesystem->remove($target);
                }
                throw $e;
            }
        } finally { $zip->close(); }
    }
    public function update(UploadedFile $upload): PluginManifest
    {
        if (!$upload->isValid() || $upload->getSize()>self::MAX_ARCHIVE || strtolower($upload->getClientOriginalExtension())!=='zip') throw new PluginPackageException('plugins.error.valid_zip');
        $zip=new ZipArchive(); if ($zip->open($upload->getPathname())!==true) throw new PluginPackageException('plugins.error.unreadable_zip');
        $filesystem=new Filesystem(); $stage=null; $backup=null; $swapped=false; $data=[];
        try {
            if ($zip->numFiles<1 || $zip->numFiles>self::MAX_FILES) throw new PluginPackageException('plugins.error.archive_count');
            $prefix=$this->packagePrefix($zip); $json=$zip->getFromName($prefix.'plugin.json');
            $data=is_string($json)?json_decode($json,true):null;
            if(!is_array($data)||!is_string($data['name']??null)||!is_string($data['version']??null))throw new PluginPackageException('plugins.error.manifest_invalid');
            $archiveManifest=PluginManifest::fromArray($data,'');if(!$archiveManifest)throw new PluginPackageException('plugins.error.manifest_invalid');
            $current=$this->registry->manifest($archiveManifest->name); if(!$current)throw new PluginPackageException('plugins.error.not_installed');
            if($this->registry->isActive($current->name))throw new PluginPackageException('plugins.error.update_requires_inactive');
            if(!version_compare($archiveManifest->version,$current->version,'>'))throw new PluginPackageException('plugins.error.update_version');
            $root=realpath($this->pluginRoot);$target=realpath($current->path);
            if(!$root||!$target||!str_starts_with($target,$root.DIRECTORY_SEPARATOR)||$current->core)throw new PluginPackageException('plugins.error.unsafe_path');
            $stage=$this->pluginRoot.'/.update-'.bin2hex(random_bytes(8));$filesystem->mkdir($stage,0750);$this->extract($zip,$prefix,$stage);
            $candidate=$this->validator->validate($stage);
            if($candidate->name!==$current->name||$candidate->version!==$data['version'])throw new PluginPackageException('plugins.error.update_identity');
            $this->compatibility->assertInstallable($candidate,$this->registry->manifests());
            $this->events->before($candidate->name,PluginLifecycleOperation::Update);
            $backupRoot=$this->projectDir.'/var/plugin-backups';$filesystem->mkdir($backupRoot,0750);$backup=$backupRoot.'/'.$current->namespace().'-'.$current->version.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
            if(!rename($target,$backup))throw new PluginPackageException('plugins.error.update_backup');
            if(!rename($stage,$target)){rename($backup,$target);throw new PluginPackageException('plugins.error.atomic_install');}
            $swapped=true;$stage=null;$candidate=$this->validator->validate($target);
            $this->migrations->migrate($candidate);$this->lifecycle->update($candidate->name,$current->version);$this->privileges->syncManifest($candidate);$this->events->after($candidate->name,PluginLifecycleOperation::Update);
            $filesystem->remove($backup);$backup=null;$this->invalidateContainer();return $candidate;
        } catch(\Throwable $exception) {
            if($stage&&is_dir($stage))$filesystem->remove($stage);
            if($swapped){$this->registry->setActive((string)($data['name']??''),false);$this->invalidateContainer();throw new PluginPackageException(sprintf('Plugin update requires recovery; the new package remains inactive and the previous package is preserved at %s. Cause: %s',(string)$backup,$exception->getMessage()),0,$exception);}
            throw $exception;
        } finally {$zip->close();}
    }
    public function uninstall(PluginManifest $plugin): void
    {
        $root=realpath($this->pluginRoot); $target=realpath($plugin->path);
        if (!$root || !$target || !str_starts_with($target,$root.DIRECTORY_SEPARATOR) || $plugin->core) throw new PluginPackageException('plugins.error.unsafe_path');
        $this->compatibility->assertUninstallable($plugin, $this->registry->manifests());
        $this->events->before($plugin->name,PluginLifecycleOperation::Uninstall);
        $this->lifecycle->deactivate($plugin->name);
        $inactive=$this->database->connection()->prepare('UPDATE plugin SET active=0 WHERE name=:name'); $inactive->execute(['name'=>$plugin->name]);
        $this->migrations->rollback($plugin); $this->lifecycle->uninstall($plugin->name);
        if ($plugin->uninstallData === 'remove') $this->configuration->delete($plugin->name);
        $this->privileges->remove($plugin->name);
        $delete=$this->database->connection()->prepare('DELETE FROM plugin WHERE name=:name'); $delete->execute(['name'=>$plugin->name]);
        (new Filesystem())->remove($target);
        $this->events->after($plugin->name,PluginLifecycleOperation::Uninstall);
        $this->invalidateContainer();
    }
    private function packagePrefix(ZipArchive $zip): string
    {
        if ($zip->locateName('plugin.json')!==false) return '';
        $roots=[]; for($i=0;$i<$zip->numFiles;$i++){ $name=str_replace('\\','/',$zip->getNameIndex($i)); $roots[]=strtok($name,'/'); }
        $roots=array_values(array_unique(array_filter($roots))); if(count($roots)!==1) throw new PluginPackageException('plugins.error.archive_layout');
        return $roots[0].'/';
    }
    private function extract(ZipArchive $zip,string $prefix,string $stage): void
    {
        $total=0;
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i); $name=str_replace('\\','/',$stat['name']??'');
            $opsys=0;$attributes=0;if($zip->getExternalAttributesIndex($i,$opsys,$attributes) && (($attributes>>16)&0170000)===0120000) throw new PluginPackageException('plugins.error.symlink');
            if (!str_starts_with($name,$prefix)) continue; $relative=substr($name,strlen($prefix)); if($relative==='') continue;
            if(str_contains($relative,"\0") || str_starts_with($relative,'/') || preg_match('#(^|/)\.\.(/|$)#',$relative) || str_contains($relative,':')) throw new PluginPackageException('plugins.error.unsafe_archive_path');
            $total+=(int)($stat['size']??0); if($total>self::MAX_EXPANDED) throw new PluginPackageException('plugins.error.expanded_too_large');
            $destination=$stage.'/'.$relative;
            if(str_ends_with($relative,'/')){(new Filesystem())->mkdir($destination,0750);continue;}
            (new Filesystem())->mkdir(dirname($destination),0750); $in=$zip->getStream($name); $out=fopen($destination,'wb'); if(!$in||!$out) throw new PluginPackageException('plugins.error.extract_failed'); stream_copy_to_stream($in,$out); fclose($in);fclose($out); chmod($destination,0640);
        }
    }
    private function invalidateContainer(): void { (new Filesystem())->remove($this->cacheDir); }
}
