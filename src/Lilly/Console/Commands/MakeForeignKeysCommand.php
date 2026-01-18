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
        parent::__construct('shape:domain:fk');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate FK migrations from the domain schema foreignKeys() definition')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));

        if ($domain === '') {
            $output->writeln('<error>Usage: shape:domain:fk <Domain></error>');
            $output->writeln('<comment>Example: shape:domain:fk Users</comment>');
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

        if (
            !method_exists($schemaFqcn, 'table')
            || !method_exists($schemaFqcn, 'ownedTables')
            || !method_exists($schemaFqcn, 'foreignKeys')
        ) {
            $output->writeln("<error>Domain schema must define table(), ownedTables(), foreignKeys():</error> {$schemaFqcn}");
            return Command::FAILURE;
        }

        $domainTable = $this->normalizeTableName((string) $schemaFqcn::table());
        if ($domainTable === '') {
            $output->writeln("<error>Invalid domain table() value in schema:</error> {$schemaFqcn}");
            return Command::FAILURE;
        }

        $ownedSet = $this->buildOwnedSet($schemaFqcn);

        $fksRaw = $schemaFqcn::foreignKeys();
        if (!is_array($fksRaw) || $fksRaw === []) {
            $output->writeln("<comment>No foreign keys defined in {$schemaFqcn}::foreignKeys().</comment>");
            return Command::SUCCESS;
        }

        $created = 0;

        foreach ($fksRaw as $fk) {
            $norm = $this->normalizeFkDefinition($fk, $schemaFqcn);

            $table = $norm['table'];
            $column = $norm['column'];
            $refTable = $norm['refTable'];
            $refColumn = $norm['refColumn'];
            $onDelete = $norm['onDelete'];
            $onUpdate = $norm['onUpdate'];

            $this->assertDomainTableAllowed($table, $domainTable, $ownedSet, $schemaFqcn);
            $this->assertCreateMigrationExists($domainRoot, $table, $domainTable);

            $needsColumn = !$this->columnAppearsInAnyMigration($domainRoot, $table, $column);

            $dir = $this->fkMigrationDir($domainRoot, $table, $domainTable);
            $this->mkdir($dir);

            $stamp = gmdate('Y_m_d_His');
            $file = "{$stamp}_fk_{$column}.php";
            $path = "{$dir}/{$file}";

            if (is_file($path)) {
                throw new RuntimeException("FK migration already exists: {$this->rel($path)}");
            }

            file_put_contents($path, $this->fkMigrationStub(
                table: $table,
                column: $column,
                refTable: $refTable,
                refColumn: $refColumn,
                onDelete: $onDelete,
                onUpdate: $onUpdate,
                needsColumn: $needsColumn
            ));

            $output->writeln(' + file ' . $this->rel($path));
            $created++;
        }

        if ($created === 0) {
            $output->writeln('<comment>No FK migrations created.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln("<info>Created {$created} FK migration(s).</info>");
        return Command::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function buildOwnedSet(string $schemaFqcn): array
    {
        $ownedRaw = $schemaFqcn::ownedTables();
        $ownedSet = [];

        if (!is_array($ownedRaw)) {
            return $ownedSet;
        }

        foreach ($ownedRaw as $t) {
            if (!is_string($t)) {
                continue;
            }
            $n = $this->normalizeTableName($t);
            if ($n !== '') {
                $ownedSet[$n] = true;
            }
        }

        return $ownedSet;
    }

    /**
     * @return array{table:string,column:string,refTable:string,refColumn:string,onDelete:?string,onUpdate:?string}
     */
    private function normalizeFkDefinition(mixed $fk, string $schemaFqcn): array
    {
        if (!is_array($fk)) {
            throw new RuntimeException("Invalid FK definition in {$schemaFqcn}::foreignKeys()");
        }

        $table = isset($fk['table']) ? $this->normalizeTableName((string) $fk['table']) : '';
        $column = isset($fk['column']) ? $this->normalizeTableName((string) $fk['column']) : '';

        $refTable = '';
        $refColumn = '';

        if (isset($fk['references']) && is_array($fk['references'])) {
            $ref = $fk['references'];
            $refTable = isset($ref['table']) ? $this->normalizeTableName((string) $ref['table']) : '';
            $refColumn = isset($ref['column']) ? $this->normalizeTableName((string) $ref['column']) : '';
        } else {
            $refTable = isset($fk['refTable']) ? $this->normalizeTableName((string) $fk['refTable']) : '';
            $refColumn = isset($fk['refColumn']) ? $this->normalizeTableName((string) $fk['refColumn']) : '';
        }

        $onDelete = isset($fk['onDelete']) ? (string) $fk['onDelete'] : null;
        $onUpdate = isset($fk['onUpdate']) ? (string) $fk['onUpdate'] : null;

        if ($table === '' || $column === '' || $refTable === '' || $refColumn === '') {
            $dump = json_encode($fk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            throw new RuntimeException("Invalid FK definition in {$schemaFqcn}::foreignKeys(): {$dump}");
        }

        return [
            'table' => $table,
            'column' => $column,
            'refTable' => $refTable,
            'refColumn' => $refColumn,
            'onDelete' => ($onDelete !== null && $onDelete !== '') ? $onDelete : null,
            'onUpdate' => ($onUpdate !== null && $onUpdate !== '') ? $onUpdate : null,
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
        ?string $onUpdate,
        bool $needsColumn
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

        if ($needsColumn) {
            $lines[] = "        \$t->unsignedBigInteger('{$column}');";
            $lines[] = "";
        }

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

    private function assertDomainTableAllowed(string $table, string $domainTable, array $ownedSet, string $schemaFqcn): void
    {
        if ($table === $domainTable) {
            return;
        }

        if (isset($ownedSet[$table])) {
            return;
        }

        throw new RuntimeException("FK table '{$table}' is not domain table or owned table in {$schemaFqcn}");
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

                if (str_contains($contents, "->create('{$table}'") || str_contains($contents, "->create(\"{$table}\"")) {
                    return true;
                }

                if (str_contains($contents, "create('{$table}'") || str_contains($contents, "create(\"{$table}\"")) {
                    return true;
                }
            }
        }

        return false;
    }

    private function columnAppearsInAnyMigration(string $domainRoot, string $table, string $column): bool
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

                if (str_contains($contents, "('{$column}'") || str_contains($contents, "(\"{$column}\"")) {
                    return true;
                }
            }
        }

        return false;
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
