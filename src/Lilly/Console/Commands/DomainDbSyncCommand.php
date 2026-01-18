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
            ->setDescription('Ensure create-table migrations exist for a domain table and its owned tables (from the domain model)')
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

            $modelFqcn = "Domains\\{$domain}\\Models\\{$domain}";
            if (!class_exists($modelFqcn)) {
                $output->writeln("<error>Domain model missing or not autoloadable:</error> {$modelFqcn}");
                continue;
            }

            if (!method_exists($modelFqcn, 'table') || !method_exists($modelFqcn, 'ownedTables')) {
                $output->writeln("<error>Domain model must define table() and ownedTables():</error> {$modelFqcn}");
                continue;
            }

            $domainTable = $this->normalizeTableName((string) $modelFqcn::table());
            $ownedTables = $modelFqcn::ownedTables();

            if ($domainTable === '') {
                $output->writeln("<error>Invalid table() for domain:</error> {$domain}");
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

            // 1) Domain table migration: Migrations/*_create.php (flat)
            $created = $this->ensureCreateMigrationFlat(
                output: $output,
                dir: $migrationsDir,
                label: $domainTable,
                table: $domainTable
            );
            $createdAny = $createdAny || $created;

            // 2) Owned tables: Migrations/owned/<table>/*_create.php
            foreach ($ownedTables as $t) {
                if (!is_string($t)) {
                    continue;
                }

                $table = $this->normalizeTableName($t);
                if ($table === '') {
                    continue;
                }

                $ownedDir = "{$migrationsDir}/owned/{$table}";
                if (!is_dir($ownedDir)) {
                    mkdir($ownedDir, 0777, true);
                    $output->writeln(' + dir  ' . $this->rel($ownedDir));
                }

                $created = $this->ensureCreateMigrationFlat(
                    output: $output,
                    dir: $ownedDir,
                    label: "owned/{$table}",
                    table: $table
                );
                $createdAny = $createdAny || $created;
            }
        }

        if (!$createdAny) {
            $output->writeln('<info>No migrations needed.</info>');
        }

        return Command::SUCCESS;
    }

    private function ensureCreateMigrationFlat(OutputInterface $output, string $dir, string $label, string $table): bool
    {
        $existingCreate = glob($dir . '/*_create.php');
        if ($existingCreate !== false && count($existingCreate) > 0) {
            $output->writeln(" - ok   create exists for {$label}");
            return false;
        }

        $stamp = gmdate('Y_m_d_His');
        $path = "{$dir}/{$stamp}_create.php";

        // ultra-low chance of collision, but avoid overwriting
        if (is_file($path)) {
            $stamp = gmdate('Y_m_d_His') . '01';
            $path = "{$dir}/{$stamp}_create.php";
        }

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));
        $output->writeln(" - add  create for {$label}");

        return true;
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

        $items = scandir($root);
        if ($items === false) {
            return [];
        }

        $out = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (str_starts_with($item, '.')) {
                continue;
            }

            if (is_dir($root . '/' . $item)) {
                $out[] = $item;
            }
        }

        sort($out);
        return $out;
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
