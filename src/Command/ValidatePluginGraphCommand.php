<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\PluginRegistry;
use App\Plugin\Compatibility\PluginCompatibilityException;
use App\Plugin\Compatibility\PluginCompatibilityResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:plugins:validate', description: 'Validates the complete installed plugin dependency graph')]
final class ValidatePluginGraphCommand extends Command
{
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginCompatibilityResolver $compatibility,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $manifests = $this->registry->manifests();
            $this->compatibility->assertCompatible($manifests);
            if ($input->getOption('json')) {
                $output->writeln((string) json_encode(['compatible' => true, 'plugins' => count($manifests), 'problems' => []], JSON_THROW_ON_ERROR));
            } else {
                $output->writeln(sprintf('<info>Plugin graph is compatible (%d plugin(s)).</info>', count($manifests)));
            }

            return Command::SUCCESS;
        } catch (PluginCompatibilityException $exception) {
            if ($input->getOption('json')) {
                $output->writeln((string) json_encode(['compatible' => false, 'problems' => array_map(static fn ($problem): array => ['code' => $problem->code, 'plugin' => $problem->plugin, 'message' => $problem->message, 'context' => $problem->context], $exception->problems)], JSON_THROW_ON_ERROR));
            } else {
                $output->writeln('<error>Plugin graph is incompatible:</error>');
                foreach ($exception->problems as $problem) {
                    $output->writeln(sprintf(' - [%s] %s', $problem->code, $problem->message));
                }
            }

            return Command::FAILURE;
        }
    }
}
