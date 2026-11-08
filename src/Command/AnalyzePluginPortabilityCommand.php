<?php

declare(strict_types=1);

namespace App\Command;

use App\Plugin\Development\PluginPortabilityAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugins:analyze-portability', description: 'Analyze an external plugin for a native Companyware port.')]
final class AnalyzePluginPortabilityCommand extends Command
{
    public function __construct(private readonly PluginPortabilityAnalyzer $analyzer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the external plugin source.');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit a stable machine-readable report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report = $this->analyzer->analyze((string) $input->getArgument('path'));
        } catch (\Throwable $exception) {
            $output->writeln($input->getOption('json') ? json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR) : '<error>'.$exception->getMessage().'</error>');
            return Command::FAILURE;
        }
        if ($input->getOption('json')) {
            $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('<info>%d patterns can be transformed; %d dependencies need manual mapping.</info>', $report['summary']['transformable'], $report['summary']['manual']));
        foreach ($report['manualMappings'] as $finding) $output->writeln(sprintf(' - %s: %s', $finding['file'], $finding['guidance']));
        return Command::SUCCESS;
    }
}
