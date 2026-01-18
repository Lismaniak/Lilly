<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Config\Config;
use Lilly\Database\ConnectionFactory;
use Lilly\Database\Migrations\MigrationRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbMigrateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly Config $config,
    ) {
        parent::__construct('db:migrate');
    }

    protected function configure(): void
    {
        $this->setDescription('Run all pending domain migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pdo = (new ConnectionFactory(
            config: $this->config,
            projectRoot: $this->projectRoot,
        ))->pdo();

        $runner = new MigrationRunner(projectRoot: $this->projectRoot);

        $applied = $runner->run($pdo);

        if ($applied === []) {
            $output->writeln('<info>No pending migrations.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Applied migrations:</info>');
        foreach ($applied as $name) {
            $output->writeln(' - ' . $name);
        }

        return Command::SUCCESS;
    }
}
