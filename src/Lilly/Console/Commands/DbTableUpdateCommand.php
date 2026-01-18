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
            ->setDescription('Scaffold an alter-table migration (add columns only, MVP). Table is resolved from the domain schema.')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('table', InputArgument::OPTIONAL, 'Optional. If omitted, updates the domain table from the domain schema. If provided, must be an owned table from the domain schema.')
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

        $schemaFqcn = "Domains\\{$domain}\\Schema\\{$domain}Schema";
        if (!class_exists($schemaFqcn)) {
            $output->writeln("<error>Domain schema missing or not autoloadable:</error> {$schemaFqcn}");
            return Command::FAILURE;
        }

        if (!method_exists($schemaFqcn, 'table') || !method_exists($schemaFqcn, 'ownedTables')) {
            $output->writeln("<error>Domain schema must define table() and ownedTables():</error> {$schemaFqcn}");
            return Command::FAILURE;
        }

        $domainTable = $this->normalizeTableName((string) $schemaFqcn::table());
        if ($domainTable === '') {
            $output->writeln("<error>Invalid domain table in {$schemaFqcn}::table().</error>");
            return Command::FAILURE;
        }

        $ownedRaw = $schemaFqcn::ownedTables();
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
            } else {
                $isOwnedTable = isset($ownedTables[$table]);
                if (!$isOwnedTable) {
                    $output->writeln("<error>Table '{$table}' is not an owned table of domain '{$domain}'.</error>");
                    $output->writeln("<comment>Domain table:</comment> {$domainTable}");
                    if ($ownedTables !== []) {
                        $output->writeln('<comment>Owned tables:</comment> ' . implode(', ', array_keys($ownedTables)));
                    } else {
                        $output->writeln('<comment>Owned tables:</comment> (none)');
                    }
                    return Command::FAILURE;
                }
            }
        }

        $baseMigrationsDir = "{$domainRoot}/Migrations";
        if (!is_dir($baseMigrationsDir)) {
            mkdir($baseMigrationsDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($baseMigrationsDir));
        }

        $tableDir = $isOwnedTable
            ? "{$baseMigrationsDir}/owned/{$table}"
            : $baseMigrationsDir;

        if (!is_dir($tableDir)) {
            mkdir($tableDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($tableDir));
        }

        $create = glob($tableDir . '/*_create.php');
        if ($create === false || count($create) === 0) {
            $output->writeln("<error>No create-table migration found for table '{$table}'.</error>");
            $output->writeln('<comment>Run:</comment> db:table:make ' . $domain . ($isOwnedTable ? " {$table} --owned" : ''));
            return Command::FAILURE;
        }

        $stamp = gmdate('Y_m_d_His');
        $file = $desc !== '' ? "{$stamp}_{$desc}.php" : "{$stamp}_update.php";
        $path = "{$tableDir}/{$file}";

        if (is_file($path)) {
            $output->writeln("<error>Migration already exists:</error> {$this->rel($path)}");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($table));

        $output->writeln(' + file ' . $this->rel($path));
        $label = $isOwnedTable ? "owned/{$table}" : "Migrations";
        $output->writeln("<info>Created migration:</info> {$domain}/{$label}/{$file}");

        return Command::SUCCESS;
    }

    private function stub(string $table): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Database\\Schema\\Schema;\nuse Lilly\\Database\\Schema\\Blueprint;\n\nreturn function (PDO \$pdo): void {\n    \$schema = new Schema(\$pdo);\n\n    \$schema->table('{$table}', function (Blueprint \$t): void {\n        // add columns here (MVP)\n        // \$t->string('example');\n    });\n};\n";
    }

    private function normalizeDesc(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';
        $raw = preg_replace('/_+/', '_', $raw) ?? '';
        $raw = trim($raw, '_');

        return $raw;
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
