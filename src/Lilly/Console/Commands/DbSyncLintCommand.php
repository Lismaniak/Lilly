<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Database\SchemaSync\SchemaSyncDiscovery;
use Lilly\Database\SchemaSync\SchemaSyncManifest;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSyncLintCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:sync:lint');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Validate table blueprints without generating migrations')
            ->addArgument('domain', InputArgument::OPTIONAL, 'Optional. Domain name, e.g. Users. If omitted, lints all domains.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arg = $input->getArgument('domain');
        $domain = is_string($arg) ? $this->normalizeDomainName($arg) : '';

        $discovery = new SchemaSyncDiscovery($this->projectRoot);
        $manifest = new SchemaSyncManifest($this->projectRoot);

        $domains = $domain !== '' ? [$domain] : $discovery->discoverDomains();
        if ($domains === []) {
            $output->writeln('<comment>No domains found in src/Domains.</comment>');
            return Command::SUCCESS;
        }

        $ok = true;

        foreach ($domains as $d) {
            $domainRoot = "{$this->projectRoot}/src/Domains/{$d}";
            if (!is_dir($domainRoot)) {
                $output->writeln("<error>Domain does not exist:</error> {$d}");
                $ok = false;
                continue;
            }

            $tables = $discovery->discoverTableBlueprints($d);
            if ($tables === []) {
                $output->writeln("<comment>Skip {$d}:</comment> no *Table classes found");
                continue;
            }

            try {
                $manifest->buildDesiredManifest($d, $tables);
                $output->writeln("<info>{$d}:</info> OK");
            } catch (\Throwable $e) {
                $output->writeln("<error>{$d}:</error> " . $this->formatError($e));
                $ok = false;
            }
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function formatError(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message !== '') {
            return $message;
        }

        return $e instanceof RuntimeException ? 'Runtime error' : 'Unknown error';
    }
}
