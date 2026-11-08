<?php
declare(strict_types=1);
namespace App\Command;
use App\Infrastructure\PluginRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name: 'app:plugins:inspect', description: 'Shows one installed plugin manifest and state')]
final class InspectPluginCommand extends Command
{
    public function __construct(private readonly PluginRegistry $registry) { parent::__construct(); }
    protected function configure():void{$this->addArgument('name',InputArgument::REQUIRED)->addOption('json',null,InputOption::VALUE_NONE,'Emit machine-readable JSON');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $name=(string)$input->getArgument('name');$manifest=$this->registry->manifest($name);
        if(!$manifest){$message=['found'=>false,'name'=>$name,'error'=>'Plugin not found.'];$output->writeln($input->getOption('json')?(string)json_encode($message,JSON_THROW_ON_ERROR):'<error>Plugin not found.</error>');return Command::FAILURE;}
        $data=['found'=>true,'name'=>$manifest->name,'label'=>$manifest->label,'version'=>$manifest->version,'active'=>$this->registry->isActive($name),'path'=>$manifest->path,'compatibility'=>['companyware'=>$manifest->companyware,'php'=>$manifest->php,'extensions'=>$manifest->extensions],'requires'=>$manifest->requires,'optional'=>$manifest->optional,'conflicts'=>$manifest->conflicts,'privileges'=>$manifest->privileges,'themes'=>$manifest->themes,'migrations'=>$manifest->migrations,'uninstall_data'=>$manifest->uninstallData];
        if($input->getOption('json'))$output->writeln((string)json_encode($data,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));else foreach($data as $key=>$value)$output->writeln(sprintf('<comment>%s:</comment> %s',$key,is_scalar($value)?(string)$value:(string)json_encode($value,JSON_UNESCAPED_SLASHES)));
        return Command::SUCCESS;
    }
}
