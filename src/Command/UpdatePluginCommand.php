<?php
declare(strict_types=1);
namespace App\Command;
use App\Plugin\PluginPackageInstaller;
use App\Security\SecurityAuditLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
#[AsCommand(name:'app:plugins:update',description:'Updates an inactive plugin from a local ZIP package')]
final class UpdatePluginCommand extends Command
{
    public function __construct(private readonly PluginPackageInstaller $installer,private readonly SecurityAuditLogger $audit){parent::__construct();}
    protected function configure():void{$this->addArgument('archive',InputArgument::REQUIRED,'Path to the newer plugin ZIP')->addOption('json',null,InputOption::VALUE_NONE,'Emit machine-readable JSON');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {try{$path=realpath((string)$input->getArgument('archive'));if($path===false||!is_file($path))throw new \InvalidArgumentException('Plugin archive does not exist.');$plugin=$this->installer->update(new UploadedFile($path,basename($path),'application/zip',null,true));$this->audit->log('plugin.updated','plugin',$plugin->name,['channel'=>'cli','version'=>$plugin->version]);$result=['success'=>true,'name'=>$plugin->name,'version'=>$plugin->version,'active'=>false];$output->writeln($input->getOption('json')?(string)json_encode($result,JSON_THROW_ON_ERROR):sprintf('<info>Updated %s to %s; activate it explicitly after verification.</info>',$plugin->name,$plugin->version));return Command::SUCCESS;}catch(\Throwable $e){$output->writeln($input->getOption('json')?(string)json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES):'<error>'.$e->getMessage().'</error>');return Command::FAILURE;}}
}
