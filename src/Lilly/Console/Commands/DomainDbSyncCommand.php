<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DomainDbSyncCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:domain:db');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Ensure create-table migrations exist for a domain table and its owned tables (from the domain schema)')
            ->addArgument('domain', InputArgument::OPTIONAL, 'Optional. Domain name, e.g. Users. If omitted, syncs all domains.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arg = $input->getArgument('domain');
        $domainArg = is_string($arg) ? $this->normalizeDomainName($arg) : '';

        $domains = $domainArg !== '' ? [$domainArg] : $this->discoverDomains();

        if ($domains === []) {
            $output->writeln('<comment>No domains found in src/Domains.</comment>');
            return Command::SUCCESS;
        }

        $createdAny = false;

        foreach ($domains as $domain) {
            $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
            if (!is_dir($domainRoot)) {
                $output->writeln("<error>Domain does not exist:</error> {$domain}");
                continue;
            }

            $schemaFqcn = "Domains\\{$domain}\\Schema\\{$domain}Schema";
            if (!class_exists($schemaFqcn)) {
                $output->writeln("<error>Domain schema missing or not autoloadable:</error> {$schemaFqcn}");
                continue;
            }

            if (!method_exists($schemaFqcn, 'table') || !method_exists($schemaFqcn, 'ownedTables')) {
                $output->writeln("<error>Domain schema must define table() and ownedTables():</error> {$schemaFqcn}");
                continue;
            }

            $domainTable = $this->normalizeTableName((string) $schemaFqcn::table());
            $ownedTables = $schemaFqcn::ownedTables();

            if ($domainTable === '') {
                $output->writeln("<error>Invalid table() for domain schema:</error> {$domain}");
                continue;
            }

            if (!is_array($ownedTables)) {
                $ownedTables = [];
            }

            $output->writeln("<info>Sync domain:</info> {$domain}");

            $migrationsDir = "{$domainRoot}/Migrations";
            if (!is_dir($migrationsDir)) {
                mkdir($migrationsDir, 0777, true);
                $output->writeln(' + dir  ' . $this->rel($migrationsDir));
            }

            if (!$this->hasCreateMigration($migrationsDir, $domainTable)) {
                $createdAny = true;
                $this->writeCreateMigration($migrationsDir, $domainTable, $output);
            }

            foreach ($ownedTables as $rawOwned) {
                if (!is_string($rawOwned)) {
                    continue;
                }

                $owned = $this->normalizeTableName($rawOwned);
                if ($owned === '') {
                    continue;
                }

                $ownedDir = "{$migrationsDir}/owned/{$owned}";
                if (!is_dir($ownedDir)) {
                    mkdir($ownedDir, 0777, true);
                    $output->writeln(' + dir  ' . $this->rel($ownedDir));
                }

                if (!$this->hasCreateMigration($ownedDir, $owned)) {
                    $createdAny = true;
                    $this->writeCreateMigration($ownedDir, $owned, $output);
                }
            }
        }

        if (!$createdAny) {
            $output->writeln('<info>All domains already have create-table migrations.</info>');
        }

        return Command::SUCCESS;
    }

    private function hasCreateMigration(string $dir, string $table): bool
    {
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, "->create('{$table}'") || str_contains($contents, "->create(\"{$table}\"")) {
                return true;
            }
        }

        return false;
    }

    private function writeCreateMigration(string $dir, string $table, OutputInterface $output): void
    {
        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_create.php";
        $path = "{$dir}/{$file}";

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));
    }

    private function stub(string $table): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Database\\Schema\\Schema;\nuse Lilly\\Database\\Schema\\Blueprint;\n\nreturn function (PDO \$pdo): void {\n    \$schema = new Schema(\$pdo);\n\n    \$schema->create('{$table}', function (Blueprint \$t): void {\n        \$t->id();\n        \$t->timestamps();\n    });\n};\n";
    }

    /**
     * @return list<string>
     */
    private function discoverDomains(): array
    {
        $root = "{$this->projectRoot}/src/Domains";
        if (!is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $domains = [];
        foreach ($entries as $e) {
            if (!is_string($e) || $e === '.' || $e === '..') {
                continue;
            }
            if (!is_dir("{$root}/{$e}")) {
                continue;
            }
            $name = $this->normalizeDomainName($e);
            if ($name !== '') {
                $domains[] = $name;
            }
        }

        sort($domains);
        return $domains;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function normalizeTableName(string $raw): string
    {
        $raw = trim($raw);
        $raw = strtolower($raw);
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';
        $raw = preg_replace('/_+/', '_', $raw) ?? '';
        $raw = trim($raw, '_');

        return $raw;
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
