<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbTableUpdateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:table:update');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold an alter-table migration (add columns only, MVP). Table is resolved from the domain model.')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('table', InputArgument::OPTIONAL, 'Optional. If omitted, updates the domain table from the domain model. If provided, must be an owned table from the domain model.')
            ->addOption('desc', null, InputOption::VALUE_REQUIRED, 'Filename description, e.g. add_verified_at');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));

        $tableArg = $input->getArgument('table');
        $tableInput = is_string($tableArg) ? $this->normalizeTableName($tableArg) : '';

        $descOpt = $input->getOption('desc');
        $descRaw = is_string($descOpt) ? trim($descOpt) : '';
        $desc = $this->normalizeDesc($descRaw);

        if ($domain === '') {
            $output->writeln('<error>Usage: db:table:update <Domain> [table] [--desc something]</error>');
            $output->writeln('<comment>Examples:</comment>');
            $output->writeln('<comment>  db:table:update Users</comment>');
            $output->writeln('<comment>  db:table:update Users --desc add_email</comment>');
            $output->writeln('<comment>  db:table:update Users user_emails</comment>');
            $output->writeln('<comment>  db:table:update Users user_emails --desc add_verified_at</comment>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $modelFqcn = "Domains\\{$domain}\\Models\\{$domain}";
        if (!class_exists($modelFqcn)) {
            $output->writeln("<error>Domain model missing or not autoloadable:</error> {$modelFqcn}");
            return Command::FAILURE;
        }

        if (!method_exists($modelFqcn, 'table') || !method_exists($modelFqcn, 'ownedTables')) {
            $output->writeln("<error>Domain model must define table() and ownedTables():</error> {$modelFqcn}");
            return Command::FAILURE;
        }

        $domainTable = $this->normalizeTableName((string) $modelFqcn::table());
        if ($domainTable === '') {
            $output->writeln("<error>Invalid domain table in {$modelFqcn}::table().</error>");
            return Command::FAILURE;
        }

        $ownedRaw = $modelFqcn::ownedTables();
        $ownedTables = [];

        if (is_array($ownedRaw)) {
            foreach ($ownedRaw as $t) {
                if (!is_string($t)) {
                    continue;
                }
                $n = $this->normalizeTableName($t);
                if ($n !== '') {
                    $ownedTables[$n] = true;
                }
            }
        }

        $isOwnedTable = false;

        if ($tableInput === '') {
            $table = $domainTable;
        } else {
            $table = $tableInput;

            if ($table === $domainTable) {
                // Allow explicit domain table name, but it is still treated as domain table update.
                $isOwnedTable = false;
            } elseif (isset($ownedTables[$table])) {
                $isOwnedTable = true;
            } else {
                $output->writeln("<error>Table '{$table}' is not part of domain '{$domain}'.</error>");
                $output->writeln("<comment>Domain table:</comment> {$domainTable}");
                if ($ownedTables !== []) {
                    $output->writeln('<comment>Owned tables:</comment> ' . implode(', ', array_keys($ownedTables)));
                } else {
                    $output->writeln('<comment>Owned tables:</comment> (none)');
                }
                return Command::FAILURE;
            }
        }

        $baseMigrationsDir = "{$domainRoot}/Migrations";
        if (!is_dir($baseMigrationsDir)) {
            mkdir($baseMigrationsDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($baseMigrationsDir));
        }

        $dir = $isOwnedTable
            ? "{$baseMigrationsDir}/owned/{$table}"
            : $baseMigrationsDir;

        if (!is_dir($dir)) {
            if ($isOwnedTable) {
                $output->writeln("<error>Owned table folder does not exist:</error> {$this->rel($dir)}");
                $output->writeln("<comment>Create it first with: db:table:make {$domain} {$table}</comment>");
                return Command::FAILURE;
            }

            // Domain migrations folder exists by now, so this should be unreachable.
            mkdir($dir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($dir));
        }

        if (!$this->hasCreateMigration($dir)) {
            $output->writeln("<error>No create-table migration found for table '{$table}'.</error>");
            $output->writeln("<comment>Run: db:table:make {$domain}" . ($isOwnedTable ? " {$table}" : '') . "</comment>");
            return Command::FAILURE;
        }

        $label = $isOwnedTable ? "owned/{$table}" : 'Migrations';

        return $this->writeUpdateMigration(
            output: $output,
            dir: $dir,
            domain: $domain,
            label: $label,
            table: $table,
            desc: $desc
        );
    }

    private function writeUpdateMigration(
        OutputInterface $output,
        string $dir,
        string $domain,
        string $label,
        string $table,
        string $desc
    ): int {
        $stamp = gmdate('Y_m_d_His');
        $file = $desc !== '' ? "{$stamp}_update_{$desc}.php" : "{$stamp}_update.php";
        $path = "{$dir}/{$file}";

        if (is_file($path)) {
            $output->writeln("<error>Migration already exists:</error> {$this->rel($path)}");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));
        $output->writeln("<info>Created migration:</info> {$domain}/{$label}/{$file}");

        return Command::SUCCESS;
    }

    private function hasCreateMigration(string $dir): bool
    {
        $create = glob($dir . '/*_create.php');
        return $create !== false && count($create) > 0;
    }

    private function stub(string $table): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Database\\Schema\\Schema;\nuse Lilly\\Database\\Schema\\Blueprint;\n\nreturn function (PDO \$pdo): void {\n    \$schema = new Schema(\$pdo);\n\n    \$schema->table('{$table}', function (Blueprint \$t): void {\n        // \$t->string('example');\n    });\n};\n";
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

    private function normalizeDesc(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

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
