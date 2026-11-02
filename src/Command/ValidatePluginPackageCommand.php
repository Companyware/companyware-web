<?php

declare(strict_types=1);

namespace App\Command;

use App\Plugin\PluginPackageValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugins:validate-package', description: 'Validates one plugin package for development, CI and distribution')]
final class ValidatePluginPackageCommand extends Command
{
    public function __construct(private readonly PluginPackageValidator $validator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Plugin package directory', '.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = realpath((string) $input->getArgument('path'));
        try {
            if ($path === false || !is_dir($path)) throw new \InvalidArgumentException('Plugin package directory does not exist.');
            $manifest = $this->validator->validate($path);
            $result = ['valid' => true, 'name' => $manifest->name, 'version' => $manifest->version, 'path' => $path];
            if ($input->getOption('json')) $output->writeln((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            else $output->writeln(sprintf('<info>%s %s is a valid Companyware plugin package.</info>', $manifest->name, $manifest->version));
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $result = ['valid' => false, 'error' => $exception->getMessage(), 'path' => $path ?: (string) $input->getArgument('path')];
            if ($input->getOption('json')) $output->writeln((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            else $output->writeln('<error>'.$exception->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
