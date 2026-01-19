<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Database\SchemaSync\SchemaSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSyncDiscardCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:sync:discard');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Delete a pending migration plan')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('hash', InputArgument::REQUIRED, 'Pending plan hash (folder name under .pending)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $hash = trim((string) $input->getArgument('hash'));

        if ($domain === '' || $hash === '') {
            $output->writeln('<error>Usage: db:sync:discard <Domain> <hash></error>');
            return Command::FAILURE;
        }

        $sync = new SchemaSync(projectRoot: $this->projectRoot);
        $result = $sync->discard(domain: $domain, hash: $hash);

        foreach ($result->lines as $line) {
            $output->writeln($line);
        }

        return $result->ok ? Command::SUCCESS : Command::FAILURE;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }
}
