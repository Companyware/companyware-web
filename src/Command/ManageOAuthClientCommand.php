<?php
declare(strict_types=1);
namespace App\Command;
use App\Api\OAuthClientRepository;use Symfony\Component\Console\Attribute\AsCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Input\InputOption;use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:api:oauth-client:manage',description:'Creates, lists or revokes OAuth2 clients')]
final class ManageOAuthClientCommand extends Command
{
    public function __construct(private readonly OAuthClientRepository $clients){parent::__construct();}
    protected function configure():void{$this->addArgument('username',InputArgument::REQUIRED)->addOption('create',null,InputOption::VALUE_REQUIRED,'Create a client with this name')->addOption('revoke',null,InputOption::VALUE_REQUIRED,'Client database ID to revoke')->addOption('json',null,InputOption::VALUE_NONE);}
    protected function execute(InputInterface $input,OutputInterface $output):int{try{$created=null;if($name=$input->getOption('create'))$created=$this->clients->create((string)$input->getArgument('username'),(string)$name);if($id=$input->getOption('revoke'))$this->clients->revoke((int)$id);$output->writeln((string)json_encode(['created'=>$created,'clients'=>$this->clients->list((string)$input->getArgument('username'))],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));return Command::SUCCESS;}catch(\Throwable $e){$output->writeln('<error>'.$e->getMessage().'</error>');return Command::FAILURE;}}
}
