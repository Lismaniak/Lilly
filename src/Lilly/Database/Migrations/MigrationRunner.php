<?php
declare(strict_types=1);

namespace Lilly\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly string $projectRoot,
    ) {}

    /**
     * @return list<string> applied migration names
     */
    public function run(PDO $pdo): array
    {
        $this->ensureMigrationsTable($pdo);

        $all = $this->discoverMigrationFiles();
        if ($all === []) {
            return [];
        }

        $applied = $this->appliedMigrationNames($pdo);

        $pending = [];
        foreach ($all as $name => $path) {
            if (!isset($applied[$name])) {
                $pending[$name] = $path;
            }
        }

        if ($pending === []) {
            return [];
        }

        $pending = $this->sortMigrationsByTimestamp($pending);

        $batch = $this->nextBatchNumber($pdo);

        foreach ($pending as $name => $path) {
            $this->printProgress($name);

            $callable = require $path;

            if (!is_callable($callable)) {
                throw new RuntimeException("Migration file must return callable: {$path}");
            }

            try {
                $callable($pdo);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Migration failed: {$name}\n{$path}\n" . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }

            $this->markApplied($pdo, $name, $batch);
        }

        return array_keys($pending);
    }

    /**
     * @return array<string, array{path: string, status: string, batch: int|null, applied_at: string|null}>
     */
    public function status(PDO $pdo): array
    {
        $this->ensureMigrationsTable($pdo);

        $all = $this->discoverMigrationFiles();
        $applied = $this->appliedMigrations($pdo);

        $out = [];

        foreach ($all as $name => $path) {
            if (isset($applied[$name])) {
                $out[$name] = [
                    'path' => $path,
                    'status' => 'applied',
                    'batch' => (int) $applied[$name]['batch'],
                    'applied_at' => (string) $applied[$name]['applied_at'],
                ];
                continue;
            }

            $out[$name] = [
                'path' => $path,
                'status' => 'pending',
                'batch' => null,
                'applied_at' => null,
            ];
        }

        return $out;
    }

    private function printProgress(string $name): void
    {
        print "[migrate] {$name}\n";
        if (function_exists('flush')) {
            flush();
        }
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS lilly_migrations (
                    name TEXT PRIMARY KEY,
                    batch INTEGER NOT NULL,
                    applied_at TEXT NOT NULL
                )"
            );
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS lilly_migrations (
                    name VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    applied_at DATETIME NOT NULL,
                    PRIMARY KEY (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return;
        }

        throw new RuntimeException("Unsupported driver '{$driver}'");
    }

    /**
     * Discovers migrations for every domain.
     *
     * Convention:
     * - Domain table migrations live flat:
     *   src/Domains/<Domain>/Database/Migrations/*.php
     *
     * - Owned tables live scoped:
     *   src/Domains/<Domain>/Database/Migrations/owned/<table>/*.php
     *
     * Migration name is stable and includes the relative path:
     * - <Domain>/<file>.php
     * - <Domain>/owned/<table>/<file>.php
     *
     * Note: ".pending" is ignored.
     *
     * @return array<string, string> [migrationName => absolutePath]
     */
    private function discoverMigrationFiles(): array
    {
        $domainsRoot = $this->projectRoot . '/src/Domains';
        if (!is_dir($domainsRoot)) {
            return [];
        }

        $domains = scandir($domainsRoot);
        if ($domains === false) {
            return [];
        }

        $files = [];

        foreach ($domains as $domain) {
            if ($domain === '.' || $domain === '..') {
                continue;
            }
            if (str_starts_with($domain, '.')) {
                continue;
            }

            $domainDir = $domainsRoot . '/' . $domain;
            if (!is_dir($domainDir)) {
                continue;
            }

            $migrationsRoot = $domainDir . '/Database/Migrations';
            if (!is_dir($migrationsRoot)) {
                continue;
            }

            // 1) Flat domain migrations: Database/Migrations/*.php (ignore directories)
            $flat = glob($migrationsRoot . '/*.php');
            if ($flat !== false) {
                foreach ($flat as $path) {
                    $file = basename($path);
                    if ($file === '' || !str_ends_with($file, '.php')) {
                        continue;
                    }

                    $name = $domain . '/' . $file;
                    $files[$name] = $path;
                }
            }

            // 2) Owned migrations: Database/Migrations/owned/<table>/*.php
            $ownedRoot = $migrationsRoot . '/owned';
            if (is_dir($ownedRoot)) {
                $tables = scandir($ownedRoot);
                if ($tables === false) {
                    continue;
                }

                foreach ($tables as $table) {
                    if ($table === '.' || $table === '..') {
                        continue;
                    }
                    if (str_starts_with($table, '.')) {
                        continue;
                    }
                    if ($table === '.pending') {
                        continue;
                    }

                    $tableDir = $ownedRoot . '/' . $table;
                    if (!is_dir($tableDir)) {
                        continue;
                    }

                    $matches = glob($tableDir . '/*.php');
                    if ($matches === false) {
                        continue;
                    }

                    foreach ($matches as $path) {
                        $file = basename($path);
                        if ($file === '' || !str_ends_with($file, '.php')) {
                            continue;
                        }

                        $name = $domain . '/owned/' . $table . '/' . $file;
                        $files[$name] = $path;
                    }
                }
            }
        }

        // Do NOT sort here for execution order. We only want stable discovery.
        // Execution order is handled in run() via sortMigrationsByTimestamp().
        ksort($files);
        return $files;
    }

    /**
     * Sorts migrations by timestamp prefix in filename.
     *
     * Expected filename prefix:
     *   YYYY_MM_DD_HHMMSS_
     *
     * If missing, timestamp is 0 and will run first.
     *
     * @param array<string, string> $migrations [name => path]
     * @return array<string, string>
     */
    private function sortMigrationsByTimestamp(array $migrations): array
    {
        $items = [];

        foreach ($migrations as $name => $path) {
            $base = basename($path);
            $ts = $this->extractTimestampKey($base);

            $items[] = [
                'name' => $name,
                'path' => $path,
                'ts' => $ts,
                'base' => $base,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['ts'] < $b['ts']) {
                return -1;
            }
            if ($a['ts'] > $b['ts']) {
                return 1;
            }

            $c = strcmp((string) $a['base'], (string) $b['base']);
            if ($c !== 0) {
                return $c;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        $out = [];
        foreach ($items as $it) {
            $out[$it['name']] = $it['path'];
        }

        return $out;
    }

    /**
     * Turns "2026_01_18_141851_create.php" into an integer sort key:
     * 20260118141851
     */
    private function extractTimestampKey(string $filename): int
    {
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{6})_/', $filename, $m) !== 1) {
            return 0;
        }

        $key = $m[1] . $m[2] . $m[3] . $m[4];

        return (int) $key;
    }

    /**
     * @return array<string, true>
     */
    private function appliedMigrationNames(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT name FROM lilly_migrations');
        $rows = $stmt ? $stmt->fetchAll() : [];

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
                $out[$row['name']] = true;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{batch: int, applied_at: string}>
     */
    private function appliedMigrations(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT name, batch, applied_at FROM lilly_migrations');
        $rows = $stmt ? $stmt->fetchAll() : [];

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['name']) || !is_string($row['name'])) {
                continue;
            }

            $out[$row['name']] = [
                'batch' => isset($row['batch']) ? (int) $row['batch'] : 0,
                'applied_at' => isset($row['applied_at']) ? (string) $row['applied_at'] : '',
            ];
        }

        return $out;
    }

    private function nextBatchNumber(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT MAX(batch) AS max_batch FROM lilly_migrations');
        $row = $stmt ? $stmt->fetch() : null;

        $max = 0;
        if (is_array($row) && isset($row['max_batch'])) {
            $max = (int) $row['max_batch'];
        }

        return $max + 1;
    }

    private function markApplied(PDO $pdo, string $name, int $batch): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO lilly_migrations (name, batch, applied_at) VALUES (:name, :batch, :applied_at)'
        );

        $ok = $stmt->execute([
            ':name' => $name,
            ':batch' => $batch,
            ':applied_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($ok !== true) {
            throw new RuntimeException("Failed to record migration '{$name}'");
        }
    }
}
