<?php
declare(strict_types=1);
namespace App\Command;
use App\Infrastructure\PluginRegistry;
use App\Plugin\PluginStateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:plugins:activate',description:'Activates an installed plugin')]
final class ActivatePluginCommand extends Command
{
    public function __construct(private readonly PluginRegistry $registry,private readonly PluginStateManager $states){parent::__construct();}
    protected function configure():void{$this->addArgument('name',InputArgument::REQUIRED)->addOption('json',null,InputOption::VALUE_NONE,'Emit machine-readable JSON');}
    protected function execute(InputInterface $input,OutputInterface $output):int{return $this->change($input,$output);}
    private function change(InputInterface $input,OutputInterface $output):int
    {try{$name=(string)$input->getArgument('name');$plugin=$this->registry->manifest($name);if(!$plugin)throw new \RuntimeException('Plugin not found.');$this->states->activate($plugin);$message=['success'=>true,'name'=>$name,'active'=>true];$output->writeln($input->getOption('json')?(string)json_encode($message,JSON_THROW_ON_ERROR):'<info>Plugin activated.</info>');return Command::SUCCESS;}catch(\Throwable $e){$output->writeln($input->getOption('json')?(string)json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR):'<error>'.$e->getMessage().'</error>');return Command::FAILURE;}}
}
