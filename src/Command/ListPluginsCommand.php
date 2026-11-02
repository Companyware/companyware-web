<?php
declare(strict_types=1);
namespace App\Command;
use App\Infrastructure\PluginRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name: 'app:plugins:list', description: 'Lists installed Companyware plugins')]
final class ListPluginsCommand extends Command
{
    public function __construct(private readonly PluginRegistry $registry) { parent::__construct(); }
    protected function configure(): void { $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugins=$this->registry->all();
        if($input->getOption('json')){$output->writeln((string)json_encode($plugins,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));return Command::SUCCESS;}
        $rows=array_map(static fn(array $plugin):array=>[$plugin['name'],$plugin['version'],$plugin['active']?'yes':'no',$plugin['core']?'core':'plugin'],$plugins);
        (new Table($output))->setHeaders(['Name','Version','Active','Type'])->setRows($rows)->render();
        return Command::SUCCESS;
    }
}
