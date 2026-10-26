<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Schema;
use Companyware\Core\Framework\Plugin\Contract\Migration\MigrationStateProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(name: 'app:migrations:status', description: 'Shows Core and plugin migration state')]
final class MigrationStatusCommand extends Command
{
    public function __construct(private readonly Schema $schema, private readonly MigrationStateProviderInterface $states)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schema->ensure();
        $states = $this->states->states();
        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($states, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['Owner', 'Version', 'Status', 'Direction', 'Finished', 'Error']);
            foreach ($states as $state) {
                $table->addRow([$state->pluginName, $state->version, $state->status, $state->direction, $state->finishedAt ?? '—', $state->error ?? '']);
            }
            $table->render();
        }

        return array_filter($states, static fn ($state): bool => $state->status === 'failed') === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
