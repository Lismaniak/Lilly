<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Config\Config;
use Lilly\Database\ConnectionFactory;
use Lilly\Database\SchemaSync\SchemaSync;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSyncApplyCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly Config $config,
    ) {
        parent::__construct('db:sync:apply');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sandbox-run pending plan(s), then apply to real DB, then promote into Migrations/')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('hash', InputArgument::OPTIONAL, 'Optional. Pending plan hash. If omitted, applies all pending plans for the domain.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $hashArg = $input->getArgument('hash');
        $hash = is_string($hashArg) ? trim($hashArg) : '';
        $verbose = $output->isVerbose();

        if ($domain === '') {
            $output->writeln('<error>Usage: db:sync:apply <Domain> [hash]</error>');
            return Command::FAILURE;
        }

        $migrationsDir = "{$this->projectRoot}/src/Domains/{$domain}/Database/Migrations";
        $pendingRoot = "{$migrationsDir}/.pending";

        if (!is_dir($pendingRoot)) {
            $output->writeln('<info>Nothing to apply.</info> No pending folder: ' . $this->rel($pendingRoot));
            return Command::SUCCESS;
        }

        $planDirs = $hash !== ''
            ? [$pendingRoot . '/' . $hash]
            : $this->discoverPendingPlanDirs($pendingRoot);

        if ($planDirs === []) {
            $output->writeln('<info>Nothing to apply.</info> No pending plans found.');
            return Command::SUCCESS;
        }

        $connection = strtolower((string) $this->config->dbConnection);

        foreach ($planDirs as $pendingDir) {
            $planHash = basename($pendingDir);

            if (!is_dir($pendingDir)) {
                $output->writeln('<error>Pending plan not found:</error> ' . $this->rel($pendingDir));
                return Command::FAILURE;
            }

            $manifestFile = "{$pendingDir}/manifest.json";
            if (!is_file($manifestFile)) {
                $output->writeln('<error>Missing manifest.json in pending plan:</error> ' . $this->rel($manifestFile));
                return Command::FAILURE;
            }

            $pendingFiles = $this->listPendingMigrationFiles($pendingDir);
            if ($pendingFiles === []) {
                $output->writeln('<error>No migration files found in pending plan:</error> ' . $this->rel($pendingDir));
                return Command::FAILURE;
            }

            $output->writeln("<info>Plan:</info> {$domain} {$planHash} (" . count($pendingFiles) . ' migrations)');
            if ($verbose) {
                foreach ($pendingFiles as $path) {
                    $output->writeln(' - ' . basename($path));
                }
            }

            try {
                $this->runSandbox($connection, $domain, $planHash, $pendingFiles);
            } catch (\Throwable $e) {
                $output->writeln("<error>Sandbox failed ({$planHash}):</error> " . $e->getMessage());
                $output->writeln('<comment>Nothing applied to the real database. This plan stays in .pending.</comment>');
                return Command::FAILURE;
            }

            $appliedBaseline = [];
            $appliedPending = [];

            try {
                $pdoReal = (new ConnectionFactory(config: $this->config, projectRoot: $this->projectRoot))->pdo();

                // Ensure real DB has full baseline (idempotent, tracked in lilly_migrations).
                $baselineFiles = $this->listAllApprovedMigrationFiles();
                $appliedBaseline = $this->runFilesOnPdo($pdoReal, $baselineFiles);

                // Then apply this pending plan.
                $appliedPending = $this->runFilesOnPdo($pdoReal, $pendingFiles);
            } catch (\Throwable $e) {
                $output->writeln("<error>Apply to real DB failed ({$planHash}):</error> " . $e->getMessage());
                $output->writeln('<comment>This plan stays in .pending (not promoted).</comment>');
                return Command::FAILURE;
            }

            if ($appliedBaseline !== []) {
                if ($verbose) {
                    $output->writeln('<info>Real DB baseline applied:</info>');
                    foreach ($appliedBaseline as $name) {
                        $output->writeln(' - ' . $name);
                    }
                } else {
                    $output->writeln('<info>Real DB baseline applied:</info> ' . count($appliedBaseline) . ' migration(s)');
                }
            }

            if ($appliedPending !== []) {
                if ($verbose) {
                    $output->writeln('<info>Applied to real DB:</info>');
                    foreach ($appliedPending as $name) {
                        $output->writeln(' - ' . $name);
                    }
                } else {
                    $output->writeln('<info>Applied to real DB:</info> ' . count($appliedPending) . ' migration(s)');
                }
            } else {
                $output->writeln('<comment>Real DB: nothing new applied (already recorded).</comment>');
            }

            $sync = new SchemaSync(projectRoot: $this->projectRoot);
            $result = $sync->accept(domain: $domain, hash: $planHash);

            foreach ($result->lines as $line) {
                if (!$verbose && str_starts_with($line, ' + ')) {
                    continue;
                }
                $output->writeln($line);
            }

            if (!$result->ok) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<string> absolute paths to pending plan dirs, sorted
     */
    private function discoverPendingPlanDirs(string $pendingRoot): array
    {
        $items = scandir($pendingRoot);
        if ($items === false) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (str_starts_with($item, '.')) {
                continue;
            }
            $full = $pendingRoot . '/' . $item;
            if (is_dir($full)) {
                $out[] = $full;
            }
        }

        sort($out);
        return $out;
    }

    /**
     * @param list<string> $pendingFiles
     */
    private function runSandbox(string $connection, string $domain, string $hash, array $pendingFiles): void
    {
        $baselineFiles = $this->listAllApprovedMigrationFiles();

        if ($connection === 'sqlite') {
            $sandboxDir = "{$this->projectRoot}/var/db_sandbox";
            if (!is_dir($sandboxDir)) {
                mkdir($sandboxDir, 0777, true);
            }

            $sandboxPath = "{$sandboxDir}/sync_apply_{$domain}_{$hash}.sqlite";
            if (is_file($sandboxPath)) {
                unlink($sandboxPath);
            }

            $pdo = new PDO('sqlite:' . $sandboxPath, null, null, $this->pdoOptions());
            $pdo->exec('PRAGMA foreign_keys = ON;');

            try {
                // Build baseline into sandbox, then apply pending.
                $this->runFilesOnPdo($pdo, $baselineFiles);
                $this->runFilesOnPdo($pdo, $pendingFiles);
            } finally {
                $pdo = null;
                if (is_file($sandboxPath)) {
                    unlink($sandboxPath);
                }
            }

            return;
        }

        if ($connection === 'mysql') {
            $host = $this->config->dbHost;
            $port = $this->config->dbPort ?? 3306;
            $user = $this->config->dbUsername;
            $pass = $this->config->dbPassword ?? '';

            if ($host === null || $host === '') {
                throw new RuntimeException('DB_HOST is required for mysql');
            }
            if ($user === null || $user === '') {
                throw new RuntimeException('DB_USERNAME is required for mysql');
            }

            $sandboxDb = $this->env('DB_SANDBOX_DATABASE');
            if ($sandboxDb === '') {
                throw new RuntimeException('DB_SANDBOX_DATABASE is required for mysql sandbox');
            }

            $dsnSandbox = "mysql:host={$host};port={$port};dbname={$sandboxDb};charset=utf8mb4";
            $pdoSandbox = new PDO($dsnSandbox, $user, $pass, $this->pdoOptions());

            $this->wipeMysqlSchema($pdoSandbox);

            // Build baseline into sandbox, then apply pending.
            $this->runFilesOnPdo($pdoSandbox, $baselineFiles);
            $this->runFilesOnPdo($pdoSandbox, $pendingFiles);

            return;
        }

        throw new RuntimeException("Unsupported DB_CONNECTION '{$this->config->dbConnection}'");
    }

    private function env(string $key): string
    {
        $v = getenv($key);
        if ($v === false) {
            return '';
        }
        return trim((string) $v);
    }

    private function wipeMysqlSchema(PDO $pdo): void
    {
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $rows = $tables ? $tables->fetchAll(PDO::FETCH_NUM) : [];

        if ($rows === []) {
            return;
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($rows as $row) {
                if (!isset($row[0]) || !is_string($row[0])) {
                    continue;
                }
                $name = $row[0];
                $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $name) . '`');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Runs a list of migration files on a PDO connection.
     * Each migration file name is recorded as "<Domain>/<FileName>".
     *
     * @param list<string> $files absolute paths
     * @return list<string> applied migration names
     */
    private function runFilesOnPdo(PDO $pdo, array $files): array
    {
        $this->ensureMigrationsTable($pdo);

        $applied = $this->appliedMigrationNames($pdo);
        $batch = $this->nextBatchNumber($pdo);
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        $namesApplied = [];

        $useTx = ($driver === 'sqlite');
        if ($useTx) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($files as $path) {
                $base = basename($path);

                $domain = $this->domainFromMigrationPath($path);
                if ($domain === '') {
                    throw new RuntimeException("Could not infer domain for migration: {$path}");
                }

                $name = "{$domain}/{$base}";

                if (isset($applied[$name])) {
                    continue;
                }

                $callable = require $path;
                if (!is_callable($callable)) {
                    throw new RuntimeException("Migration file must return callable: {$path}");
                }

                $callable($pdo);

                $this->markApplied($pdo, $name, $batch);
                $namesApplied[] = $name;
            }

            if ($useTx && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($useTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $namesApplied;
    }

    /**
     * @return list<string> absolute paths to ALL approved migration files across ALL domains
     */
    private function listAllApprovedMigrationFiles(): array
    {
        $root = "{$this->projectRoot}/src/Domains";
        if (!is_dir($root)) {
            return [];
        }

        $domains = scandir($root) ?: [];
        $files = [];

        foreach ($domains as $d) {
            if ($d === '.' || $d === '..') {
                continue;
            }
            if (str_starts_with($d, '.')) {
                continue;
            }

            $migrationsDir = "{$root}/{$d}/Database/Migrations";
            if (!is_dir($migrationsDir)) {
                continue;
            }

            foreach (glob($migrationsDir . '/*.php') ?: [] as $path) {
                $files[] = $path;
            }
        }

        usort($files, function (string $a, string $b): int {
            $ba = basename($a);
            $bb = basename($b);
            $c = strcmp($ba, $bb);
            if ($c !== 0) {
                return $c;
            }
            return strcmp($a, $b);
        });

        return array_values($files);
    }

    private function domainFromMigrationPath(string $path): string
    {
        $needle = '/src/Domains/';
        $pos = strpos($path, $needle);
        if ($pos === false) {
            return '';
        }

        $rest = substr($path, $pos + strlen($needle));
        if ($rest === false || $rest === '') {
            return '';
        }

        $parts = explode('/', $rest);
        if (!isset($parts[0]) || $parts[0] === '') {
            return '';
        }

        return (string) $parts[0];
    }

    /**
     * @return list<string>
     */
    private function listPendingMigrationFiles(string $pendingDir): array
    {
        $files = glob($pendingDir . '/*.php') ?: [];
        usort($files, fn (string $a, string $b): int => strcmp(basename($a), basename($b)));
        return array_values($files);
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS lilly_migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    applied_at TEXT NOT NULL
                )"
            );
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS lilly_migrations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    applied_at VARCHAR(32) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return;
        }

        throw new RuntimeException("Unsupported driver '{$driver}'");
    }

    /**
     * @return array<string, bool>
     */
    private function appliedMigrationNames(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT name FROM lilly_migrations');
        $rows = $stmt ? $stmt->fetchAll() : [];

        $out = [];
        foreach ($rows as $r) {
            if (is_array($r) && isset($r['name']) && is_string($r['name'])) {
                $out[$r['name']] = true;
            }
        }

        return $out;
    }

    private function nextBatchNumber(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT MAX(batch) AS b FROM lilly_migrations');
        $row = $stmt ? $stmt->fetch() : null;

        $max = 0;
        if (is_array($row) && isset($row['b']) && is_numeric($row['b'])) {
            $max = (int) $row['b'];
        }

        return $max + 1;
    }

    private function markApplied(PDO $pdo, string $name, int $batch): void
    {
        $appliedAt = gmdate('c');

        $stmt = $pdo->prepare('INSERT INTO lilly_migrations (name, batch, applied_at) VALUES (:name, :batch, :applied_at)');
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare migrations insert');
        }

        $stmt->execute([
            ':name' => $name,
            ':batch' => $batch,
            ':applied_at' => $appliedAt,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
