<?php

namespace App\Command;

use App\Infrastructure\AdminRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(name: 'app:admin:create', description: 'Creates or updates an administrator account')]
final class CreateAdminCommand extends Command
{
    public function __construct(private readonly AdminRepository $admins)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED)->addArgument('display-name', InputArgument::OPTIONAL, '', 'Administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = new Question('Password: ');
        $question->setHidden(true);
        $password = (string) $this->getHelper('question')->ask($input, $output, $question);
        if (strlen($password) < 10) {
            $output->writeln('<error>The password must contain at least 10 characters.</error>');
            return Command::INVALID;
        }
        $this->admins->save((string) $input->getArgument('username'), $password, (string) $input->getArgument('display-name'));
        $output->writeln('<info>Administrator saved.</info>');
        return Command::SUCCESS;
    }
}
