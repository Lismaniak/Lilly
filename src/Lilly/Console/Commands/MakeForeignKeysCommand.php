<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeForeignKeysCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:domain:db:fk');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate foreign key migrations from Domain::foreignKeys()')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        if ($domain === '') {
            $output->writeln('<error>Invalid domain name.</error>');
            return Command::FAILURE;
        }

        $domainRoot = $this->projectRoot . '/src/Domains/' . $domain;
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain}");
            return Command::FAILURE;
        }

        $modelFqcn = "Domains\\{$domain}\\Models\\{$domain}";
        if (!class_exists($modelFqcn)) {
            $output->writeln("<error>Domain model not found:</error> {$modelFqcn}");
            return Command::FAILURE;
        }

        if (!method_exists($modelFqcn, 'foreignKeys')) {
            $output->writeln("<error>{$modelFqcn}::foreignKeys() missing.</error>");
            return Command::FAILURE;
        }

        if (!method_exists($modelFqcn, 'ownedTables') || !method_exists($modelFqcn, 'table')) {
            $output->writeln("<error>{$modelFqcn} must have table() and ownedTables().</error>");
            return Command::FAILURE;
        }

        $domainTable = (string) $modelFqcn::table();

        $owned = $modelFqcn::ownedTables();
        $ownedSet = $this->buildOwnedSet($owned);

        $fks = $modelFqcn::foreignKeys();
        if (!is_array($fks) || $fks === []) {
            $output->writeln("<info>No foreign keys defined for:</info> {$domain}");
            return Command::SUCCESS;
        }

        $count = 0;
        $now = time();

        foreach ($fks as $fk) {
            $def = $this->normalizeFkDefinition($fk, $modelFqcn);

            $this->assertDomainTableAllowed($def['table'], $domainTable, $ownedSet, $modelFqcn);
            $this->assertDomainTableAllowed($def['refTable'], $domainTable, $ownedSet, $modelFqcn);

            // Guard: create migrations must exist before FK migrations are generated
            $this->assertCreateMigrationExists($domainRoot, $def['table'], $domainTable);
            $this->assertCreateMigrationExists($domainRoot, $def['refTable'], $domainTable);
            $this->assertColumnExistsInCreateMigration($domainRoot, $def['table'], $domainTable, $def['column']);

            $targetDir = $this->fkMigrationDir($domainRoot, $def['table'], $domainTable);
            $this->mkdir($targetDir);

            $stamp = date('Y_m_d_His', $now + $count);
            $file = "{$stamp}_fk_{$def['table']}_{$def['column']}.php";
            $path = $targetDir . '/' . $file;

            if (file_exists($path)) {
                $output->writeln(" = skip {$this->rel($path)} (exists)");
                $count++;
                continue;
            }

            file_put_contents(
                $path,
                $this->fkMigrationStub(
                    $def['table'],
                    $def['column'],
                    $def['refTable'],
                    $def['refColumn'],
                    $def['onDelete'],
                    $def['onUpdate']
                )
            );

            $output->writeln(" + file {$this->rel($path)}");
            $count++;
        }

        $output->writeln("<info>Generated foreign key migrations:</info> {$count}");
        return Command::SUCCESS;
    }

    /**
     * @param mixed $owned
     * @return array<string, true>
     */
    private function buildOwnedSet(mixed $owned): array
    {
        if (!is_array($owned)) {
            throw new RuntimeException('ownedTables() must return list<string>');
        }

        $set = [];
        foreach ($owned as $t) {
            if (!is_string($t) || trim($t) === '') {
                throw new RuntimeException('ownedTables() must return list<string>');
            }
            $set[$t] = true;
        }

        return $set;
    }

    /**
     * @param mixed $fk
     * @return array{
     *   table: string,
     *   column: string,
     *   refTable: string,
     *   refColumn: string,
     *   onDelete: string|null,
     *   onUpdate: string|null
     * }
     */
    private function normalizeFkDefinition(mixed $fk, string $modelFqcn): array
    {
        if (!is_array($fk)) {
            throw new RuntimeException("Invalid FK definition in {$modelFqcn}::foreignKeys()");
        }

        $table = isset($fk['table']) ? (string) $fk['table'] : '';
        $column = isset($fk['column']) ? (string) $fk['column'] : '';

        $ref = $fk['references'] ?? null;
        $refTable = is_array($ref) ? (string) ($ref['table'] ?? '') : '';
        $refColumn = is_array($ref) ? (string) ($ref['column'] ?? '') : '';

        $onDelete = isset($fk['onDelete']) ? (string) $fk['onDelete'] : null;
        $onUpdate = isset($fk['onUpdate']) ? (string) $fk['onUpdate'] : null;

        if (trim($table) === '' || trim($column) === '' || trim($refTable) === '' || trim($refColumn) === '') {
            throw new RuntimeException("Invalid FK definition in {$modelFqcn}::foreignKeys()");
        }

        return [
            'table' => $table,
            'column' => $column,
            'refTable' => $refTable,
            'refColumn' => $refColumn,
            'onDelete' => $onDelete !== '' ? $onDelete : null,
            'onUpdate' => $onUpdate !== '' ? $onUpdate : null,
        ];
    }

    private function fkMigrationDir(string $domainRoot, string $table, string $domainTable): string
    {
        if ($table === $domainTable) {
            return $domainRoot . '/Migrations';
        }

        return $domainRoot . '/Migrations/owned/' . $table;
    }

    private function fkMigrationStub(
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
        ?string $onDelete,
        ?string $onUpdate
    ): string {
        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "";
        $lines[] = "use Lilly\\Database\\Schema\\Blueprint;";
        $lines[] = "use Lilly\\Database\\Schema\\Schema;";
        $lines[] = "";
        $lines[] = "return function (PDO \$pdo): void {";
        $lines[] = "    \$schema = new Schema(\$pdo);";
        $lines[] = "";
        $lines[] = "    \$schema->table('{$table}', function (Blueprint \$t): void {";
        $lines[] = "        \$fk = \$t->foreign('{$column}')->references('{$refTable}', '{$refColumn}');";

        if ($onDelete !== null && $onDelete !== '') {
            $lines[] = "        \$fk->onDelete('{$onDelete}');";
        }
        if ($onUpdate !== null && $onUpdate !== '') {
            $lines[] = "        \$fk->onUpdate('{$onUpdate}');";
        }

        $lines[] = "    });";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    private function assertDomainTableAllowed(string $table, string $domainTable, array $ownedSet, string $modelFqcn): void
    {
        if ($table === $domainTable) {
            return;
        }

        if (isset($ownedSet[$table])) {
            return;
        }

        throw new RuntimeException("FK table '{$table}' is not domain table or owned table in {$modelFqcn}");
    }

    private function assertCreateMigrationExists(string $domainRoot, string $table, string $domainTable): void
    {
        if ($this->hasCreateMigrationForTable($domainRoot, $table, $domainTable)) {
            return;
        }

        $hintDir = ($table === $domainTable)
            ? 'src/Domains/<Domain>/Migrations'
            : "src/Domains/<Domain>/Migrations/owned/{$table}";

        throw new RuntimeException("No create migration found for table '{$table}'. Create the table migration first in {$hintDir}.");
    }

    private function hasCreateMigrationForTable(string $domainRoot, string $table, string $domainTable): bool
    {
        $dirs = [
            $domainRoot . '/Migrations',
            $domainRoot . '/Migrations/owned/' . $table,
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                // Accept Schema->create('<table>') calls (this is your convention)
                if (str_contains($contents, "->create('{$table}'") || str_contains($contents, "->create(\"{$table}\"")) {
                    return true;
                }

                // Fallback: Schema::create('<table>' style
                if (str_contains($contents, "create('{$table}'") || str_contains($contents, "create(\"{$table}\"")) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assertColumnExistsInCreateMigration(string $domainRoot, string $table, string $domainTable, string $column): void
    {
        if ($this->columnAppearsInCreateMigration($domainRoot, $table, $domainTable, $column)) {
            return;
        }

        throw new RuntimeException(
            "FK column '{$column}' not found in create migration for table '{$table}'. Create the column first, then generate FK migrations."
        );
    }

    private function columnAppearsInCreateMigration(string $domainRoot, string $table, string $domainTable, string $column): bool
    {
        foreach ($this->candidateCreateMigrationFiles($domainRoot, $table, $domainTable) as $file) {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            // crude but effective: look for builder calls with ('column')
            if (str_contains($contents, "('{$column}'") || str_contains($contents, "(\"{$column}\"")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function candidateCreateMigrationFiles(string $domainRoot, string $table, string $domainTable): array
    {
        $dirs = [
            $domainRoot . '/Migrations',
            $domainRoot . '/Migrations/owned/' . $table,
        ];

        $out = [];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                if (str_contains($contents, "->create('{$table}'") || str_contains($contents, "->create(\"{$table}\"")) {
                    $out[] = $file;
                }
            }
        }

        return $out;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function rel(string $absPath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absPath), '/');
    }
}
