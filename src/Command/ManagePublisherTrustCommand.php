<?php
declare(strict_types=1);
namespace App\Command;
use App\Extension\PublisherTrustStore;use Symfony\Component\Console\Attribute\AsCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Input\InputOption;use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:extensions:publisher',description:'Trust, revoke or list extension publisher keys')]
final class ManagePublisherTrustCommand extends Command
{
 public function __construct(private readonly PublisherTrustStore $trust){parent::__construct();}
 protected function configure():void{$this->addArgument('action',InputArgument::OPTIONAL,'list, trust or revoke','list')->addArgument('publisher',InputArgument::OPTIONAL)->addArgument('key-id',InputArgument::OPTIONAL)->addArgument('public-key',InputArgument::OPTIONAL)->addOption('json',null,InputOption::VALUE_NONE);}
 protected function execute(InputInterface $input,OutputInterface $output):int{try{$action=(string)$input->getArgument('action');if($action==='trust')$this->trust->trust((string)$input->getArgument('publisher'),(string)$input->getArgument('key-id'),(string)$input->getArgument('public-key'));elseif($action==='revoke')$this->trust->revoke((string)$input->getArgument('publisher'),(string)$input->getArgument('key-id'));elseif($action!=='list')throw new \InvalidArgumentException('Action must be list, trust or revoke.');$data=$this->trust->all();$output->writeln($input->getOption('json')?json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):sprintf('<info>Publisher trust store contains %d publisher(s).</info>',count($data['publishers'])));return self::SUCCESS;}catch(\Throwable $e){$output->writeln('<error>'.$e->getMessage().'</error>');return self::FAILURE;}}
}
