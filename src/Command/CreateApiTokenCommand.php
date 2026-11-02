<?php
declare(strict_types=1);
namespace App\Command;
use App\Api\AccessTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Input\InputOption;use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:api:token:create',description:'Creates a personal API access token')]
final class CreateApiTokenCommand extends Command
{
    public function __construct(private readonly AccessTokenRepository $tokens){parent::__construct();}
    protected function configure():void{$this->addArgument('username',InputArgument::REQUIRED)->addArgument('name',InputArgument::REQUIRED)->addOption('expires',null,InputOption::VALUE_REQUIRED,'Expiry understood by DateTimeImmutable')->addOption('json',null,InputOption::VALUE_NONE);}
    protected function execute(InputInterface $input,OutputInterface $output):int{try{$expires=$input->getOption('expires');$token=$this->tokens->issue((string)$input->getArgument('username'),(string)$input->getArgument('name'),$expires?new \DateTimeImmutable((string)$expires):null);$data=['token'=>$token,'warning'=>'Store this token now; it cannot be shown again.'];$output->writeln($input->getOption('json')?(string)json_encode($data,JSON_THROW_ON_ERROR):$token);return Command::SUCCESS;}catch(\Throwable $e){$output->writeln('<error>'.$e->getMessage().'</error>');return Command::FAILURE;}}
}
