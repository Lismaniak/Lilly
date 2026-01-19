<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

use Lilly\Database\Schema\Blueprint;
use RuntimeException;

final class SchemaSync
{
    public function __construct(
        private readonly string $projectRoot
    ) {}

    public function generate(?string $domain = null): SchemaSyncResult
    {
        $lines = [];

        $domains = $domain !== null ? [$domain] : $this->discoverDomains();
        if ($domains === []) {
            return new SchemaSyncResult(true, ['<comment>No domains found in src/Domains.</comment>']);
        }

        foreach ($domains as $d) {
            $domainRoot = "{$this->projectRoot}/src/Domains/{$d}";
            $dbRoot = "{$domainRoot}/Database";
            $tablesDir = "{$dbRoot}/Tables";
            $migrationsDir = "{$dbRoot}/Migrations";
            $pendingRoot = "{$migrationsDir}/.pending";

            if (!is_dir($domainRoot)) {
                $lines[] = "<error>Domain does not exist:</error> {$d}";
                continue;
            }
            if (!is_dir($tablesDir)) {
                $lines[] = "<comment>Skip {$d}:</comment> no Tables folder at " . $this->rel($tablesDir);
                continue;
            }

            $tables = $this->discoverTableBlueprints($d);
            if ($tables === []) {
                $lines[] = "<comment>Skip {$d}:</comment> no *Table classes found";
                continue;
            }

            $approved = $this->readApprovedManifest($d);
            $desired = $this->buildDesiredManifest($d, $tables);

            $hash = (string) $desired['schema_hash'];
            $pendingDir = "{$pendingRoot}/{$hash}";

            $this->mkdir($migrationsDir);
            $this->mkdir($pendingRoot);

            if (is_dir($pendingDir)) {
                $lines[] = "<info>{$d}:</info> pending plan already exists: {$hash}";
                continue;
            }

            $this->mkdir($pendingDir);

            $plan = [
                'domain' => $d,
                'schema_hash' => $hash,
                'generated_at' => gmdate('c'),
                'ops' => [],
            ];

            $createdFiles = 0;

            foreach ($tables as $t) {
                $tableName = $t['table'];
                $tableClass = $t['class'];

                $approvedEntry = $approved['tables'][$tableName] ?? null;
                $desiredEntry = $desired['tables'][$tableName] ?? null;

                if (!is_array($desiredEntry) || !isset($desiredEntry['def']) || !is_array($desiredEntry['def'])) {
                    throw new RuntimeException("Desired manifest missing def for table '{$tableName}'");
                }

                if ($approvedEntry === null) {
                    $file = $this->writeCreateMigration($pendingDir, $d, $tableName, $tableClass);
                    $plan['ops'][] = [
                        'op' => 'create_table',
                        'table' => $tableName,
                        'file' => $file,
                        'class' => $tableClass,
                    ];
                    $createdFiles++;
                    continue;
                }

                $approvedDef = $approvedEntry['def'] ?? null;
                if (!is_array($approvedDef)) {
                    $lines[] = "<comment>{$d}:</comment> legacy approved manifest for table '{$tableName}' has no def; cannot diff. Delete schema.manifest.json and re-approve.";
                    continue;
                }

                $desiredDef = $desiredEntry['def'];

                if ($this->defsEqual($approvedDef, $desiredDef)) {
                    continue;
                }

                $ops = $this->buildUpdateOps($approvedDef, $desiredDef);

                if ($ops['renames'] === [] && $ops['adds'] === []) {
                    continue;
                }

                $file = $this->writeUpdateMigration($pendingDir, $d, $tableName, $ops);

                $plan['ops'][] = [
                    'op' => 'update_table',
                    'table' => $tableName,
                    'file' => $file,
                    'class' => $tableClass,
                    'changes' => $ops,
                ];
                $createdFiles++;
            }

            file_put_contents(
                "{$pendingDir}/plan.json",
                json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );
            file_put_contents(
                "{$pendingDir}/manifest.json",
                json_encode($desired, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );

            if ($createdFiles === 0) {
                $this->deleteDirectory($pendingDir);
                $lines[] = "<info>{$d}:</info> nothing to generate (already approved)";
                continue;
            }

            $lines[] = "<info>{$d}:</info> generated pending plan {$hash}";
            $lines[] = " - pending dir " . $this->rel($pendingDir);
            $lines[] = " - files: {$createdFiles}";
            $lines[] = "Run: <comment>db:sync:apply {$d} {$hash}</comment> or <comment>db:sync:discard {$d} {$hash}</comment>";
        }

        return new SchemaSyncResult(true, $lines);
    }

    public function accept(string $domain, string $hash): SchemaSyncResult
    {
        $lines = [];

        $dbRoot = "{$this->projectRoot}/src/Domains/{$domain}/Database";
        $migrationsDir = "{$dbRoot}/Migrations";
        $pendingDir = "{$migrationsDir}/.pending/{$hash}";
        $approvedPath = "{$dbRoot}/schema.manifest.json";
        $logPath = "{$dbRoot}/schema.approved.log";

        if (!is_dir($pendingDir)) {
            return new SchemaSyncResult(false, ["<error>Pending plan not found:</error> " . $this->rel($pendingDir)]);
        }

        $manifestFile = "{$pendingDir}/manifest.json";
        if (!is_file($manifestFile)) {
            return new SchemaSyncResult(false, ["<error>Missing manifest.json in pending plan:</error> " . $this->rel($pendingDir)]);
        }

        $this->mkdir($migrationsDir);

        $moved = 0;
        foreach (glob($pendingDir . '/*.php') ?: [] as $file) {
            $base = basename($file);
            $dest = "{$migrationsDir}/{$base}";

            if (is_file($dest)) {
                throw new RuntimeException("Refusing to overwrite existing migration: " . $this->rel($dest));
            }

            rename($file, $dest);
            $moved++;
            $lines[] = " + file " . $this->rel($dest);
        }

        $manifest = file_get_contents($manifestFile);
        if ($manifest === false) {
            return new SchemaSyncResult(false, ["<error>Could not read manifest:</error> " . $this->rel($manifestFile)]);
        }

        file_put_contents($approvedPath, $manifest);

        $logLine = gmdate('c') . " domain={$domain} hash={$hash} moved={$moved}\n";
        file_put_contents($logPath, $logLine, FILE_APPEND);

        $this->deleteDirectory($pendingDir);

        array_unshift($lines, "<info>Accepted plan:</info> {$domain} {$hash}");
        return new SchemaSyncResult(true, $lines);
    }

    public function discard(string $domain, string $hash): SchemaSyncResult
    {
        $migrationsDir = "{$this->projectRoot}/src/Domains/{$domain}/Database/Migrations";
        $pendingDir = "{$migrationsDir}/.pending/{$hash}";

        if (!is_dir($pendingDir)) {
            return new SchemaSyncResult(false, ["<error>Pending plan not found:</error> " . $this->rel($pendingDir)]);
        }

        $this->deleteDirectory($pendingDir);

        return new SchemaSyncResult(true, [
            "<info>Discarded plan:</info> {$domain} {$hash}",
            " - deleted " . $this->rel($pendingDir),
        ]);
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
        foreach ($items as $d) {
            if ($d === '.' || $d === '..') {
                continue;
            }
            if (str_starts_with($d, '.')) {
                continue;
            }
            if (is_dir("{$root}/{$d}")) {
                $out[] = $d;
            }
        }

        sort($out);
        return $out;
    }

    /**
     * @return list<array{class: string, table: string}>
     */
    private function discoverTableBlueprints(string $domain): array
    {
        $ns = "Domains\\{$domain}\\Database\\Tables\\";
        $dir = "{$this->projectRoot}/src/Domains/{$domain}/Database/Tables";

        $files = glob($dir . '/*Table.php') ?: [];
        sort($files);

        $out = [];

        foreach ($files as $path) {
            $base = basename($path, '.php');
            $fqcn = $ns . $base;

            if (!class_exists($fqcn)) {
                continue;
            }
            if (!method_exists($fqcn, 'name') || !method_exists($fqcn, 'define')) {
                continue;
            }

            $table = (string) $fqcn::name();
            $table = trim($table);

            if ($table === '') {
                continue;
            }

            $out[] = ['class' => $fqcn, 'table' => $table];
        }

        return $out;
    }

    /**
     * @param list<array{class: string, table: string}> $tables
     * @return array<string, mixed>
     */
    private function buildDesiredManifest(string $domain, array $tables): array
    {
        $tablesMap = [];
        foreach ($tables as $t) {
            $table = $t['table'];
            $class = $t['class'];

            $tablesMap[$table] = [
                'class' => $class,
                'def' => $this->tableDefinition($table, $class),
            ];
        }
        ksort($tablesMap);

        $normalized = [
            'domain' => $domain,
            'tables' => $tablesMap,
        ];

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to json encode manifest');
        }

        $hash = hash('sha256', $json);

        return [
            'domain' => $domain,
            'generated_at' => gmdate('c'),
            'schema_hash' => $hash,
            'tables' => $tablesMap,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readApprovedManifest(string $domain): array
    {
        $path = "{$this->projectRoot}/src/Domains/{$domain}/Database/schema.manifest.json";
        if (!is_file($path)) {
            return ['tables' => []];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['tables' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['tables' => []];
        }

        if (!isset($decoded['tables']) || !is_array($decoded['tables'])) {
            $decoded['tables'] = [];
        }

        return $decoded;
    }

    /**
     * @return array{table:string, columns:list<array<string,mixed>>, was:array<string,list<string>>}
     */
    private function tableDefinition(string $tableName, string $tableClass): array
    {
        $bp = new Blueprint($tableName, 'create');

        try {
            $tableClass::define($bp);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to build blueprint for {$tableClass}: " . $e->getMessage(), previous: $e);
        }

        $cols = [];
        foreach ($bp->columns() as $c) {
            $cols[] = [
                'name' => $c->name,
                'type' => $c->type,
                'nullable' => $c->nullable,
                'unique' => $c->unique,
                'primary' => $c->primary,
                'auto_increment' => $c->autoIncrement,
                'default' => $c->default,
            ];
        }

        $cols = $this->normalizeColumnsForManifest($cols);

        return [
            'table' => $tableName,
            'columns' => $cols,
            'was' => $this->normalizeWasMap($bp->was()),
        ];
    }

    /**
     * @param array<string, list<string>> $was
     * @return array<string, list<string>>
     */
    private function normalizeWasMap(array $was): array
    {
        $out = [];
        foreach ($was as $to => $fromList) {
            if (!is_string($to)) {
                continue;
            }
            $to = trim($to);
            if ($to === '') {
                continue;
            }

            $clean = [];
            foreach ($fromList as $from) {
                if (!is_string($from)) {
                    continue;
                }
                $from = trim($from);
                if ($from === '' || $from === $to) {
                    continue;
                }
                $clean[] = $from;
            }

            $clean = array_values(array_unique($clean));
            if ($clean === []) {
                continue;
            }

            $out[$to] = $clean;
        }

        ksort($out);
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $cols
     * @return list<array<string,mixed>>
     */
    private function normalizeColumnsForManifest(array $cols): array
    {
        usort($cols, function (array $a, array $b): int {
            $an = isset($a['name']) ? (string) $a['name'] : '';
            $bn = isset($b['name']) ? (string) $b['name'] : '';
            return $an <=> $bn;
        });

        $out = [];
        foreach ($cols as $c) {
            $name = isset($c['name']) ? trim((string) $c['name']) : '';
            if ($name === '') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'type' => isset($c['type']) ? (string) $c['type'] : '',
                'nullable' => (bool) ($c['nullable'] ?? false),
                'unique' => (bool) ($c['unique'] ?? false),
                'primary' => (bool) ($c['primary'] ?? false),
                'auto_increment' => (bool) ($c['auto_increment'] ?? false),
                'default' => $c['default'] ?? null,
            ];
        }

        return $out;
    }

    private function defsEqual(array $a, array $b): bool
    {
        $ja = json_encode($a, JSON_UNESCAPED_SLASHES);
        $jb = json_encode($b, JSON_UNESCAPED_SLASHES);

        if ($ja === false || $jb === false) {
            return false;
        }

        return hash('sha256', $ja) === hash('sha256', $jb);
    }

    /**
     * @return array{renames:list<array{from:string,to:string}>, adds:list<array<string,mixed>>}
     */
    private function buildUpdateOps(array $approvedDef, array $desiredDef): array
    {
        $approvedCols = $approvedDef['columns'] ?? [];
        $desiredCols = $desiredDef['columns'] ?? [];

        if (!is_array($approvedCols) || !is_array($desiredCols)) {
            return ['renames' => [], 'adds' => []];
        }

        $approvedSet = [];
        foreach ($approvedCols as $c) {
            if (!is_array($c) || !isset($c['name'])) {
                continue;
            }
            $n = trim((string) $c['name']);
            if ($n !== '') {
                $approvedSet[$n] = true;
            }
        }

        $desiredByName = [];
        foreach ($desiredCols as $c) {
            if (!is_array($c) || !isset($c['name'])) {
                continue;
            }
            $n = trim((string) $c['name']);
            if ($n !== '') {
                $desiredByName[$n] = $c;
            }
        }

        $renames = [];
        $was = $desiredDef['was'] ?? [];
        if (!is_array($was)) {
            $was = [];
        }

        foreach ($was as $to => $fromList) {
            if (!is_string($to)) {
                continue;
            }
            $to = trim($to);
            if ($to === '') {
                continue;
            }

            if (isset($approvedSet[$to])) {
                continue;
            }

            if (!is_array($fromList)) {
                continue;
            }

            $matches = [];
            foreach ($fromList as $from) {
                if (!is_string($from)) {
                    continue;
                }
                $from = trim($from);
                if ($from === '') {
                    continue;
                }
                if (isset($approvedSet[$from])) {
                    $matches[] = $from;
                }
            }

            $matches = array_values(array_unique($matches));

            if (count($matches) > 1) {
                throw new RuntimeException(
                    "Ambiguous was() for '{$to}': multiple legacy columns exist: " . implode(', ', $matches)
                );
            }

            if (count($matches) === 1) {
                $from = $matches[0];

                if (!isset($desiredByName[$to])) {
                    throw new RuntimeException("was() target '{$to}' must exist as a desired column");
                }

                $renames[] = ['from' => $from, 'to' => $to];

                unset($approvedSet[$from]);
                $approvedSet[$to] = true;
            }
        }

        $adds = [];
        foreach ($desiredByName as $name => $col) {
            if (!isset($approvedSet[$name])) {
                $adds[] = $col;
            }
        }

        usort($adds, fn (array $a, array $b): int => ((string) ($a['name'] ?? '')) <=> ((string) ($b['name'] ?? '')));

        return [
            'renames' => $renames,
            'adds' => $adds,
        ];
    }

    private function writeCreateMigration(string $pendingDir, string $domain, string $tableName, string $tableClass): string
    {
        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_create_{$tableName}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->createTableMigrationStub($domain, $tableClass));
        return $file;
    }

    /**
     * @param array{renames:list<array{from:string,to:string}>, adds:list<array<string,mixed>>} $ops
     */
    private function writeUpdateMigration(string $pendingDir, string $domain, string $tableName, array $ops): string
    {
        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_update_{$tableName}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->updateTableMigrationStub($domain, $tableName, $ops));
        return $file;
    }

    private function createTableMigrationStub(string $domain, string $tableClass): string
    {
        $ns = "Domains\\{$domain}\\Database\\Migrations";

        $short = $tableClass;
        $pos = strrpos($tableClass, '\\');
        if ($pos !== false) {
            $short = substr($tableClass, $pos + 1);
        }

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "";
        $lines[] = "namespace {$ns};";
        $lines[] = "";
        $lines[] = "use PDO;";
        $lines[] = "use Lilly\\Database\\Schema\\Blueprint;";
        $lines[] = "use Lilly\\Database\\Schema\\Schema;";
        $lines[] = "use {$tableClass};";
        $lines[] = "";
        $lines[] = "return function (PDO \$pdo): void {";
        $lines[] = "    \$schema = new Schema(\$pdo);";
        $lines[] = "";
        $lines[] = "    \$schema->create({$short}::name(), function (Blueprint \$t): void {";
        $lines[] = "        {$short}::define(\$t);";
        $lines[] = "    });";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    /**
     * @param array{renames:list<array{from:string,to:string}>, adds:list<array<string,mixed>>} $ops
     */
    private function updateTableMigrationStub(string $domain, string $tableName, array $ops): string
    {
        $ns = "Domains\\{$domain}\\Database\\Migrations";

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "";
        $lines[] = "namespace {$ns};";
        $lines[] = "";
        $lines[] = "use PDO;";
        $lines[] = "use Lilly\\Database\\Schema\\Blueprint;";
        $lines[] = "use Lilly\\Database\\Schema\\Schema;";
        $lines[] = "";
        $lines[] = "return function (PDO \$pdo): void {";
        $lines[] = "    \$schema = new Schema(\$pdo);";
        $lines[] = "";
        $lines[] = "    \$schema->table('{$tableName}', function (Blueprint \$t): void {";

        foreach ($ops['renames'] as $r) {
            $from = $this->escapeSingleQuoted((string) ($r['from'] ?? ''));
            $to = $this->escapeSingleQuoted((string) ($r['to'] ?? ''));
            if ($from !== '' && $to !== '') {
                $lines[] = "        \$t->rename('{$from}', '{$to}');";
            }
        }

        if ($ops['renames'] !== [] && $ops['adds'] !== []) {
            $lines[] = "";
        }

        foreach ($ops['adds'] as $c) {
            if (!is_array($c) || !isset($c['name'], $c['type'])) {
                continue;
            }

            $name = trim((string) $c['name']);
            $type = trim((string) $c['type']);
            if ($name === '' || $type === '') {
                continue;
            }

            $lines[] = $this->emitAddColumnLine($c);
        }

        $lines[] = "    });";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $c
     */
    private function emitAddColumnLine(array $c): string
    {
        $name = $this->escapeSingleQuoted(trim((string) $c['name']));
        $type = trim((string) $c['type']);

        $nullable = (bool) ($c['nullable'] ?? false);
        $unique = (bool) ($c['unique'] ?? false);
        $default = $c['default'] ?? null;

        $base = $this->emitColumnFactoryCall($name, $type);

        $chain = '';
        if ($nullable) {
            $chain .= "->nullable()";
        }
        if ($unique) {
            $chain .= "->unique()";
        }
        if ($default !== null) {
            $chain .= "->default(" . $this->exportPhpValue($default) . ")";
        }

        return "        {$base}{$chain};";
    }

    private function emitColumnFactoryCall(string $name, string $type): string
    {
        if ($type === 'id') {
            return "\$t->id('{$name}')";
        }
        if ($type === 'text') {
            return "\$t->text('{$name}')";
        }
        if ($type === 'int') {
            return "\$t->int('{$name}')";
        }
        if ($type === 'bool') {
            return "\$t->boolean('{$name}')";
        }
        if ($type === 'timestamp') {
            return "\$t->timestamp('{$name}')";
        }
        if ($type === 'ubigint') {
            return "\$t->unsignedBigInteger('{$name}')";
        }
        if (str_starts_with($type, 'string:')) {
            $len = (int) substr($type, strlen('string:'));
            if ($len < 1) {
                $len = 255;
            }
            return "\$t->string('{$name}', {$len})";
        }
        if ($type === 'string') {
            return "\$t->string('{$name}')";
        }

        throw new RuntimeException("Unsupported column type '{$type}' for '{$name}' in update stub");
    }

    private function exportPhpValue(mixed $v): string
    {
        if (is_string($v)) {
            return "'" . $this->escapeSingleQuoted($v) . "'";
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if ($v === null) {
            return 'null';
        }

        return var_export($v, true);
    }

    private function escapeSingleQuoted(string $s): string
    {
        return str_replace("'", "\\'", $s);
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteDirectory($full);
                continue;
            }
            unlink($full);
        }

        rmdir($path);
    }

    private function rel(string $absPath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absPath), '/');
    }
}
