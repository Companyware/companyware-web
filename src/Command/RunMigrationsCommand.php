<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\PluginRegistry;
use App\Infrastructure\Schema;
use App\Plugin\Compatibility\PluginCompatibilityResolver;
use App\Plugin\Migration\MigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrations:migrate', description: 'Runs pending Core and installed-plugin migrations')]
final class RunMigrationsCommand extends Command
{
    public function __construct(
        private readonly Schema $schema,
        private readonly PluginRegistry $plugins,
        private readonly PluginCompatibilityResolver $compatibility,
        private readonly MigrationManager $migrations,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schema->ensure();
        $plugins = $this->plugins->manifests();
        $this->compatibility->assertCompatible($plugins);
        foreach ($plugins as $plugin) {
            $this->migrations->migrate($plugin);
        }
        $output->writeln(sprintf('<info>Core and plugin migrations are current (%d plugin(s)).</info>', count($plugins)));

        return Command::SUCCESS;
    }
}
