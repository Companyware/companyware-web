<?php
declare(strict_types=1);
namespace App\Command;
use App\Infrastructure\PluginRegistry;
use App\Plugin\PluginPackageInstaller;
use App\Security\SecurityAuditLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
#[AsCommand(name:'app:plugins:uninstall',description:'Uninstalls a Companyware plugin package')]
final class UninstallPluginCommand extends Command
{
    public function __construct(private readonly PluginRegistry $registry,private readonly PluginPackageInstaller $installer,private readonly SecurityAuditLogger $audit){parent::__construct();}
    protected function configure():void{$this->addArgument('name',InputArgument::REQUIRED)->addOption('yes','y',InputOption::VALUE_NONE,'Confirm destructive uninstall')->addOption('json',null,InputOption::VALUE_NONE,'Emit machine-readable JSON');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        try{$name=(string)$input->getArgument('name');$plugin=$this->registry->manifest($name);if(!$plugin)throw new \RuntimeException('Plugin not found.');$confirmed=(bool)$input->getOption('yes');if(!$confirmed&&$input->isInteractive())$confirmed=(bool)$this->getHelper('question')->ask($input,$output,new ConfirmationQuestion(sprintf('Uninstall %s and apply its "%s" data policy? [y/N] ',$name,$plugin->uninstallData),false));if(!$confirmed)throw new \RuntimeException('Uninstall was not confirmed; pass --yes for non-interactive operation.');$this->installer->uninstall($plugin);$this->audit->log('plugin.uninstalled','plugin',$name,['channel'=>'cli','data_policy'=>$plugin->uninstallData]);$result=['success'=>true,'name'=>$name,'uninstalled'=>true,'data_policy'=>$plugin->uninstallData];$output->writeln($input->getOption('json')?(string)json_encode($result,JSON_THROW_ON_ERROR):sprintf('<info>Uninstalled %s.</info>',$name));return Command::SUCCESS;}catch(\Throwable $e){$output->writeln($input->getOption('json')?(string)json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR):'<error>'.$e->getMessage().'</error>');return Command::FAILURE;}
    }
}
