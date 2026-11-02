<?php

declare(strict_types=1);

namespace App\Command;

use App\Plugin\Development\PluginSkeletonGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugins:create', description: 'Generates a complete Companyware plugin skeleton')]
final class CreatePluginCommand extends Command
{
    public function __construct(private readonly PluginSkeletonGenerator $generator, private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Lowercase plugin name')
            ->addOption('vendor', null, InputOption::VALUE_REQUIRED, 'Composer vendor', 'acme')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Target directory')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $directory = (string) ($input->getOption('directory') ?: $this->projectDir.'/plugins/'.str_replace(' ', '', ucwords(str_replace('-', ' ', $name))));
        try {
            $files = $this->generator->generate($name, (string) $input->getOption('vendor'), $directory);
            $result = ['created' => true, 'name' => $name, 'directory' => $directory, 'files' => $files];
            if ($input->getOption('json')) $output->writeln((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            else $output->writeln(sprintf('<info>Created %s with %d files in %s.</info>', $name, count($files), $directory));
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ($input->getOption('json')) $output->writeln((string) json_encode(['created' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR));
            else $output->writeln('<error>'.$exception->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
