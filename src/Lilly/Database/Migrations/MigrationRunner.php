<?php
declare(strict_types=1);

namespace Lilly\Database\Migrations;

use PDO;
use RuntimeException;

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

        $batch = $this->nextBatchNumber($pdo);

        $pdo->beginTransaction();

        try {
            foreach ($pending as $name => $path) {
                $callable = require $path;

                if (!is_callable($callable)) {
                    throw new RuntimeException("Migration file must return callable: {$path}");
                }

                $callable($pdo);

                $this->markApplied($pdo, $name, $batch);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
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
            } else {
                $out[$name] = [
                    'path' => $path,
                    'status' => 'pending',
                    'batch' => null,
                    'applied_at' => null,
                ];
            }
        }

        return $out;
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS lilly_migrations (
                name TEXT PRIMARY KEY,
                batch INTEGER NOT NULL,
                applied_at TEXT NOT NULL
            )"
        );
    }

    /**
     * Discovers migrations for every domain.
     *
     * Convention:
     * - Domain table migrations live flat:
     *   src/Domains/<Domain>/Migrations/*.php
     *
     * - Owned tables live scoped:
     *   src/Domains/<Domain>/Migrations/owned/<table>/*.php
     *
     * Migration name is stable and includes the relative path:
     * - <Domain>/<file>.php
     * - <Domain>/owned/<table>/<file>.php
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

            $migrationsRoot = $domainDir . '/Migrations';
            if (!is_dir($migrationsRoot)) {
                continue;
            }

            // 1) Flat domain migrations: Migrations/*.php (ignore directories)
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

            // 2) Owned migrations: Migrations/owned/<table>/*.php
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

        ksort($files);
        return $files;
    }

    /**
     * Collects migrations from:
     * - <baseDir>/<table>/*.php
     *
     * Writes keys as:
     * - <namePrefix>/<table>/<file>
     *
     * @param array<string, string> $files
     */
    private function collectTableMigrations(array &$files, string $domain, string $baseDir, string $namePrefix): void
    {
        $tables = scandir($baseDir);
        if ($tables === false) {
            return;
        }

        foreach ($tables as $table) {
            if ($table === '.' || $table === '..') {
                continue;
            }
            if (str_starts_with($table, '.')) {
                continue;
            }

            $tableDir = $baseDir . '/' . $table;
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

                $name = $namePrefix . '/' . $table . '/' . $file;
                $files[$name] = $path;
            }
        }
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
            ':applied_at' => gmdate('c'),
        ]);

        if ($ok !== true) {
            throw new RuntimeException("Failed to record migration '{$name}'");
        }
    }
}
