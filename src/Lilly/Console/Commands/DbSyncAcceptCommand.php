<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Database\SchemaSync\SchemaSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSyncAcceptCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:sync:accept');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Promote a pending migration plan into the real migrations folder and approve its manifest')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('hash', InputArgument::REQUIRED, 'Pending plan hash (folder name under .pending)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $hash = trim((string) $input->getArgument('hash'));

        if ($domain === '' || $hash === '') {
            $output->writeln('<error>Usage: db:sync:accept <Domain> <hash></error>');
            return Command::FAILURE;
        }

        $sync = new SchemaSync(projectRoot: $this->projectRoot);
        $result = $sync->accept(domain: $domain, hash: $hash);

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
