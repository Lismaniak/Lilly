<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Database\SchemaSync\SchemaSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSyncCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:sync');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate pending migrations from domain table blueprints (no DB changes)')
            ->addArgument('domain', InputArgument::OPTIONAL, 'Optional. Domain name, e.g. Users. If omitted, syncs all domains.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arg = $input->getArgument('domain');
        $domain = is_string($arg) ? $this->normalizeDomainName($arg) : '';

        $sync = new SchemaSync(projectRoot: $this->projectRoot);
        $result = $sync->generate(domain: $domain !== '' ? $domain : null);

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
