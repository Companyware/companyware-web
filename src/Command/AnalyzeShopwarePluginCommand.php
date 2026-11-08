<?php

declare(strict_types=1);

namespace App\Command;

use App\Plugin\Development\ShopwarePortabilityAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugins:analyze-shopware', description: 'Analyze the effort required to port a Shopware plugin to Companyware.')]
final class AnalyzeShopwarePluginCommand extends Command
{
    public function __construct(private readonly ShopwarePortabilityAnalyzer $analyzer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the Shopware plugin.');
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
        $output->writeln(sprintf('<info>%d administration patterns map directly; %d patterns need manual domain adaptation.</info>', $report['summary']['direct'], $report['summary']['manual']));
        foreach ($report['manualMappings'] as $finding) $output->writeln(sprintf(' - %s: %s', $finding['file'], $finding['guidance']));
        return Command::SUCCESS;
    }
}
