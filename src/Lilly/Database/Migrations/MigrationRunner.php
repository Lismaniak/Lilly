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
     * @return array<string, string> [migrationName => absolutePath]
     */
    private function discoverMigrationFiles(): array
    {
        $root = $this->projectRoot . '/src/Domains';

        if (!is_dir($root)) {
            return [];
        }

        $domains = scandir($root);
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

            $dir = $root . '/' . $domain . '/Migrations';
            if (!is_dir($dir)) {
                continue;
            }

            $matches = glob($dir . '/*.php');
            if ($matches === false) {
                continue;
            }

            foreach ($matches as $path) {
                $name = $domain . '/' . basename($path);

                $files[$name] = $path;
            }
        }

        ksort($files);

        return $files;
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
        $stmt = $pdo->prepare('INSERT INTO lilly_migrations (name, batch, applied_at) VALUES (:name, :batch, :applied_at)');
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
