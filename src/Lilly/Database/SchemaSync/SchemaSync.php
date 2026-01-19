<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

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

            $hash = $desired['schema_hash'];
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

            $created = 0;

            foreach ($tables as $t) {
                $tableName = $t['table'];
                $tableClass = $t['class'];

                if (isset($approved['tables'][$tableName])) {
                    continue;
                }

                $stamp = gmdate('Y_m_d_His');
                $file = "{$stamp}_create_{$tableName}.php";
                $path = "{$pendingDir}/{$file}";

                file_put_contents($path, $this->createTableMigrationStub($d, $tableClass));

                $plan['ops'][] = [
                    'op' => 'create_table',
                    'table' => $tableName,
                    'file' => $file,
                    'class' => $tableClass,
                ];

                $created++;
            }

            file_put_contents("{$pendingDir}/plan.json", json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            file_put_contents("{$pendingDir}/manifest.json", json_encode($desired, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

            if ($created === 0) {
                $this->deleteDirectory($pendingDir);
                $lines[] = "<info>{$d}:</info> nothing to generate (already approved)";
                continue;
            }

            $lines[] = "<info>{$d}:</info> generated pending plan {$hash}";
            $lines[] = " - pending dir " . $this->rel($pendingDir);
            $lines[] = " - files: {$created}";
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
            $tablesMap[$t['table']] = [
                'class' => $t['class'],
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
