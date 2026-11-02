<?php
declare(strict_types=1);
namespace App\Command;
use App\Api\AccessTokenRepository;use Symfony\Component\Console\Attribute\AsCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Input\InputOption;use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:api:token:manage',description:'Lists or revokes personal API tokens')]
final class ManageApiTokenCommand extends Command
{
    public function __construct(private readonly AccessTokenRepository $tokens){parent::__construct();}
    protected function configure():void{$this->addArgument('username',InputArgument::REQUIRED)->addOption('revoke',null,InputOption::VALUE_REQUIRED,'Token ID to revoke')->addOption('json',null,InputOption::VALUE_NONE);}
    protected function execute(InputInterface $input,OutputInterface $output):int{try{if($id=$input->getOption('revoke'))$this->tokens->revoke((int)$id);$rows=$this->tokens->list((string)$input->getArgument('username'));$output->writeln((string)json_encode(['tokens'=>$rows],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));return Command::SUCCESS;}catch(\Throwable $e){$output->writeln('<error>'.$e->getMessage().'</error>');return Command::FAILURE;}}
}
