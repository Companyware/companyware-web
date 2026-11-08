<?php
declare(strict_types=1);
namespace App\Command;
use App\Extension\ExtensionUpdateService;use Symfony\Component\Console\Attribute\AsCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Input\InputOption;use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:extensions:updates',description:'Lists or installs trusted compatible extension updates')]
final class ExtensionUpdatesCommand extends Command
{
 public function __construct(private readonly ExtensionUpdateService $updates){parent::__construct();}
 protected function configure():void{$this->addArgument('plugin',InputArgument::OPTIONAL,'Inactive plugin to update')->addOption('apply',null,InputOption::VALUE_NONE,'Download, verify and install the selected update')->addOption('registry',null,InputOption::VALUE_REQUIRED,'HTTPS registry URL or local test file')->addOption('json',null,InputOption::VALUE_NONE);}
 protected function execute(InputInterface $input,OutputInterface $output):int{try{$name=$input->getArgument('plugin');$result=$input->getOption('apply')?$this->updates->update((string)$name,$input->getOption('registry')):$this->updates->status($input->getOption('registry'));$output->writeln($input->getOption('json')?json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):($input->getOption('apply')?sprintf('<info>Updated %s to %s; activate explicitly after verification.</info>',$result['name'],$result['version']):sprintf('<info>%d installed extension(s) inspected.</info>',count($result))));return self::SUCCESS;}catch(\Throwable $e){$output->writeln($input->getOption('json')?json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR):'<error>'.$e->getMessage().'</error>');return self::FAILURE;}}
}
