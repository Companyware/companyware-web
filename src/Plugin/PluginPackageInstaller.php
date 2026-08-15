<?php
namespace App\Plugin;
use App\Infrastructure\Database;
use App\Plugin\Migration\MigrationManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;
final class PluginPackageInstaller
{
    private const MAX_ARCHIVE=20_000_000; private const MAX_EXPANDED=100_000_000; private const MAX_FILES=1000;
    private readonly string $pluginRoot;
    public function __construct(string $projectDir, private readonly string $cacheDir, private readonly MigrationManager $migrations, private readonly Database $database, private readonly PluginLifecycleManager $lifecycle)
    { $this->pluginRoot=$projectDir.'/plugins'; }
    public function install(UploadedFile $upload): PluginManifest
    {
        if (!$upload->isValid() || $upload->getSize()>self::MAX_ARCHIVE || strtolower($upload->getClientOriginalExtension())!=='zip') throw new PluginPackageException('Es ist eine gültige ZIP-Datei bis 20 MB erforderlich.');
        $zip=new ZipArchive(); if ($zip->open($upload->getPathname())!==true) throw new PluginPackageException('Das ZIP-Archiv kann nicht gelesen werden.');
        try {
            if ($zip->numFiles<1 || $zip->numFiles>self::MAX_FILES) throw new PluginPackageException('Das Archiv enthält zu viele oder keine Dateien.');
            $prefix=$this->packagePrefix($zip); $manifestName=$prefix.'plugin.json'; $json=$zip->getFromName($manifestName);
            if (!is_string($json)) throw new PluginPackageException('plugin.json fehlt im Paket.');
            $data=json_decode($json,true); if (!is_array($data) || !preg_match('/^[a-z][a-z0-9-]+$/',$data['name']??'') || !is_string($data['label']??null) || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/',$data['version']??'')) throw new PluginPackageException('plugin.json ist ungültig.');
            $directory=str_replace(' ','',ucwords(str_replace('-',' ',$data['name']))); $target=$this->pluginRoot.'/'.$directory;
            if (file_exists($target)) throw new PluginPackageException('Das Plugin ist bereits installiert.');
            $stage=$this->pluginRoot.'/.install-'.bin2hex(random_bytes(8)); (new Filesystem())->mkdir($stage,0750);
            try { $this->extract($zip,$prefix,$stage); $manifest=PluginManifest::fromFile($stage.'/plugin.json'); if (!$manifest) throw new PluginPackageException('Das Manifest konnte nicht validiert werden.'); if(!rename($stage,$target))throw new PluginPackageException('Das Plugin konnte nicht atomar installiert werden.'); $manifest=PluginManifest::fromFile($target.'/plugin.json'); if(!$manifest)throw new PluginPackageException('Das installierte Manifest ist ungültig.'); $this->migrations->migrate($manifest); $this->invalidateContainer(); return $manifest; }
            catch (\Throwable $e) { $filesystem=new Filesystem();$filesystem->remove($stage);if(is_dir($target))$filesystem->remove($target);throw $e; }
        } finally { $zip->close(); }
    }
    public function uninstall(PluginManifest $plugin): void
    {
        $root=realpath($this->pluginRoot); $target=realpath($plugin->path);
        if (!$root || !$target || !str_starts_with($target,$root.DIRECTORY_SEPARATOR) || $plugin->core) throw new PluginPackageException('Pluginpfad ist nicht sicher.');
        $this->lifecycle->deactivate($plugin->name); $this->migrations->rollback($plugin); $this->lifecycle->uninstall($plugin->name);
        $delete=$this->database->connection()->prepare('DELETE FROM plugin WHERE name=:name'); $delete->execute(['name'=>$plugin->name]);
        (new Filesystem())->remove($target);
        $this->invalidateContainer();
    }
    private function packagePrefix(ZipArchive $zip): string
    {
        if ($zip->locateName('plugin.json')!==false) return '';
        $roots=[]; for($i=0;$i<$zip->numFiles;$i++){ $name=str_replace('\\','/',$zip->getNameIndex($i)); $roots[]=strtok($name,'/'); }
        $roots=array_values(array_unique(array_filter($roots))); if(count($roots)!==1) throw new PluginPackageException('Das Archiv muss plugin.json im Stamm oder genau einen Pluginordner enthalten.');
        return $roots[0].'/';
    }
    private function extract(ZipArchive $zip,string $prefix,string $stage): void
    {
        $total=0;
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i); $name=str_replace('\\','/',$stat['name']??'');
            $opsys=0;$attributes=0;if($zip->getExternalAttributesIndex($i,$opsys,$attributes) && (($attributes>>16)&0170000)===0120000) throw new PluginPackageException('Symbolische Links sind in Pluginpaketen nicht erlaubt.');
            if (!str_starts_with($name,$prefix)) continue; $relative=substr($name,strlen($prefix)); if($relative==='') continue;
            if(str_contains($relative,"\0") || str_starts_with($relative,'/') || preg_match('#(^|/)\.\.(/|$)#',$relative) || str_contains($relative,':')) throw new PluginPackageException('Unsicherer Dateipfad im Archiv.');
            $total+=(int)($stat['size']??0); if($total>self::MAX_EXPANDED) throw new PluginPackageException('Entpackte Plugin-Dateien sind zu groß.');
            $destination=$stage.'/'.$relative;
            if(str_ends_with($relative,'/')){(new Filesystem())->mkdir($destination,0750);continue;}
            (new Filesystem())->mkdir(dirname($destination),0750); $in=$zip->getStream($name); $out=fopen($destination,'wb'); if(!$in||!$out) throw new PluginPackageException('Plugin-Datei kann nicht entpackt werden.'); stream_copy_to_stream($in,$out); fclose($in);fclose($out); chmod($destination,0640);
        }
    }
    private function invalidateContainer(): void { (new Filesystem())->remove($this->cacheDir); }
}
