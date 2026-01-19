<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

use RuntimeException;

final class SchemaSync
{
    private SchemaSyncFs $fs;
    private SchemaSyncDiscovery $discovery;
    private SchemaSyncManifest $manifest;
    private SchemaSyncPlanner $planner;
    private SchemaSyncWriter $writer;

    public function __construct(
        private readonly string $projectRoot
    ) {
        $this->fs = new SchemaSyncFs($projectRoot);
        $this->discovery = new SchemaSyncDiscovery($projectRoot);
        $this->manifest = new SchemaSyncManifest($projectRoot);
        $this->planner = new SchemaSyncPlanner();
        $this->writer = new SchemaSyncWriter();
    }

    public function generate(?string $domain = null): SchemaSyncResult
    {
        $lines = [];

        $domains = $domain !== null ? [$domain] : $this->discovery->discoverDomains();
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
                $lines[] = "<comment>Skip {$d}:</comment> no Tables folder at " . $this->fs->rel($tablesDir);
                continue;
            }

            $tables = $this->discovery->discoverTableBlueprints($d);
            if ($tables === []) {
                $lines[] = "<comment>Skip {$d}:</comment> no *Table classes found";
                continue;
            }

            $approved = $this->manifest->readApprovedManifest($d);
            $desired = $this->manifest->buildDesiredManifest($d, $tables);

            $hash = (string) $desired['schema_hash'];
            $pendingDir = "{$pendingRoot}/{$hash}";

            $this->fs->mkdir($migrationsDir);
            $this->fs->mkdir($pendingRoot);

            if (is_dir($pendingDir)) {
                $lines[] = "<info>{$d}:</info> pending plan already exists: {$hash}";
                continue;
            }

            $this->fs->mkdir($pendingDir);

            $plan = [
                'domain' => $d,
                'schema_hash' => $hash,
                'generated_at' => gmdate('c'),
                'ops' => [],
            ];

            $createdFiles = 0;
            $desiredTables = array_fill_keys(array_keys($desired['tables'] ?? []), true);
            $approvedTables = array_keys($approved['tables'] ?? []);

            $tablesByName = [];
            $createTables = [];
            $updateTables = [];

            foreach ($tables as $t) {
                $tableName = $t['table'];
                $tablesByName[$tableName] = $t;

                if (!isset($approved['tables'][$tableName])) {
                    $createTables[] = $tableName;
                } else {
                    $updateTables[] = $tableName;
                }
            }

            $createOrder = $this->orderCreateTables(
                $createTables,
                $desired['tables'] ?? []
            );

            $stampBase = time();
            $stampOffset = 0;

            foreach ($createOrder as $tableName) {
                $table = $tablesByName[$tableName] ?? null;
                if ($table === null) {
                    continue;
                }

                $tableClass = $table['class'];
                $desiredEntry = $desired['tables'][$tableName] ?? null;

                if (!is_array($desiredEntry) || !isset($desiredEntry['def']) || !is_array($desiredEntry['def'])) {
                    throw new RuntimeException("Desired manifest missing def for table '{$tableName}'");
                }

                $desiredDef = $desiredEntry['def'];
                $stamp = gmdate('Y_m_d_His', $stampBase + $stampOffset);
                $stampOffset++;

                $file = $this->writer->writeCreateMigration($pendingDir, $d, $tableName, $desiredDef, $stamp);

                $plan['ops'][] = [
                    'op' => 'create_table',
                    'table' => $tableName,
                    'file' => $file,
                    'class' => $tableClass,
                ];
                $createdFiles++;
            }

            foreach ($updateTables as $tableName) {
                $table = $tablesByName[$tableName] ?? null;
                if ($table === null) {
                    continue;
                }

                $tableClass = $table['class'];

                $approvedEntry = $approved['tables'][$tableName] ?? null;
                $desiredEntry = $desired['tables'][$tableName] ?? null;

                if (!is_array($desiredEntry) || !isset($desiredEntry['def']) || !is_array($desiredEntry['def'])) {
                    throw new RuntimeException("Desired manifest missing def for table '{$tableName}'");
                }

                $desiredDef = $desiredEntry['def'];

                $approvedDef = $approvedEntry['def'] ?? null;
                if (!is_array($approvedDef)) {
                    $lines[] = "<comment>{$d}:</comment> legacy approved manifest for table '{$tableName}' has no def; cannot diff. Delete schema.manifest.json and re-approve.";
                    continue;
                }

                if ($this->planner->defsEqual($approvedDef, $desiredDef)) {
                    continue;
                }

                $ops = $this->planner->buildUpdateOps($approvedDef, $desiredDef);

                if (
                    ($ops['drops'] ?? []) === []
                    && ($ops['renames'] ?? []) === []
                    && ($ops['adds'] ?? []) === []
                    && ($ops['changes'] ?? []) === []
                    && ($ops['foreign_keys_adds'] ?? []) === []
                    && ($ops['foreign_keys_drops'] ?? []) === []
                ) {
                    continue;
                }

                $file = $this->writer->writeUpdateMigration($pendingDir, $d, $tableName, $ops);

                $plan['ops'][] = [
                    'op' => 'update_table',
                    'table' => $tableName,
                    'file' => $file,
                    'class' => $tableClass,
                    'changes' => $ops,
                ];
                $createdFiles++;
            }

            foreach ($approvedTables as $tableName) {
                if (isset($desiredTables[$tableName])) {
                    continue;
                }

                $file = $this->writer->writeDropMigration($pendingDir, $d, $tableName);

                $plan['ops'][] = [
                    'op' => 'drop_table',
                    'table' => $tableName,
                    'file' => $file,
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
                $this->fs->deleteDirectory($pendingDir);
                $lines[] = "<info>{$d}:</info> nothing to generate (already approved)";
                continue;
            }

            $lines[] = "<info>{$d}:</info> generated pending plan {$hash}";
            $lines[] = " - pending dir " . $this->fs->rel($pendingDir);
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
            return new SchemaSyncResult(false, ["<error>Pending plan not found:</error> " . $this->fs->rel($pendingDir)]);
        }

        $manifestFile = "{$pendingDir}/manifest.json";
        if (!is_file($manifestFile)) {
            return new SchemaSyncResult(false, ["<error>Missing manifest.json in pending plan:</error> " . $this->fs->rel($pendingDir)]);
        }

        $this->fs->mkdir($migrationsDir);

        $moved = 0;
        foreach (glob($pendingDir . '/*.php') ?: [] as $file) {
            $base = basename($file);
            $dest = "{$migrationsDir}/{$base}";

            if (is_file($dest)) {
                throw new RuntimeException("Refusing to overwrite existing migration: " . $this->fs->rel($dest));
            }

            rename($file, $dest);
            $moved++;
            $lines[] = " + file " . $this->fs->rel($dest);
        }

        $manifest = file_get_contents($manifestFile);
        if ($manifest === false) {
            return new SchemaSyncResult(false, ["<error>Could not read manifest:</error> " . $this->fs->rel($manifestFile)]);
        }

        file_put_contents($approvedPath, $manifest);

        $logLine = gmdate('c') . " domain={$domain} hash={$hash} moved={$moved}\n";
        file_put_contents($logPath, $logLine, FILE_APPEND);

        $this->fs->deleteDirectory($pendingDir);

        array_unshift($lines, "<info>Accepted plan:</info> {$domain} {$hash}");
        return new SchemaSyncResult(true, $lines);
    }

    public function discard(string $domain, string $hash): SchemaSyncResult
    {
        $migrationsDir = "{$this->projectRoot}/src/Domains/{$domain}/Database/Migrations";
        $pendingDir = "{$migrationsDir}/.pending/{$hash}";

        if (!is_dir($pendingDir)) {
            return new SchemaSyncResult(false, ["<error>Pending plan not found:</error> " . $this->fs->rel($pendingDir)]);
        }

        $this->fs->deleteDirectory($pendingDir);

        return new SchemaSyncResult(true, [
            "<info>Discarded plan:</info> {$domain} {$hash}",
            " - deleted " . $this->fs->rel($pendingDir),
        ]);
    }

    /**
     * @param list<string> $createTables
     * @param array<string, array<string, mixed>> $desiredTables
     * @return list<string>
     */
    private function orderCreateTables(array $createTables, array $desiredTables): array
    {
        $createTables = array_values(array_unique($createTables));
        if ($createTables === []) {
            return [];
        }

        $createSet = array_fill_keys($createTables, true);
        $edges = [];
        $inDegree = [];

        foreach ($createTables as $table) {
            $edges[$table] = [];
            $inDegree[$table] = 0;
        }

        foreach ($createTables as $table) {
            $entry = $desiredTables[$table] ?? null;
            $def = is_array($entry) ? ($entry['def'] ?? null) : null;
            if (!is_array($def)) {
                continue;
            }

            $fks = $def['foreign_keys'] ?? [];
            if (!is_array($fks)) {
                continue;
            }

            foreach ($fks as $fk) {
                if (!is_array($fk)) {
                    continue;
                }
                $on = trim((string) ($fk['on'] ?? ''));
                if ($on === '' || $on === $table) {
                    continue;
                }
                if (!isset($createSet[$on])) {
                    continue;
                }

                $edges[$on][] = $table;
                $inDegree[$table]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }
        sort($queue);

        $ordered = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $ordered[] = $current;

            foreach ($edges[$current] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }

            if ($queue !== []) {
                sort($queue);
            }
        }

        if (count($ordered) !== count($createTables)) {
            $remaining = array_values(array_diff($createTables, $ordered));
            sort($remaining);
            $ordered = array_merge($ordered, $remaining);
        }

        return $ordered;
    }
}
