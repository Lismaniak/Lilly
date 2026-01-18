<?php
declare(strict_types=1);

namespace Lilly\Database;

use Lilly\Config\Config;
use PDO;
use PDOException;
use RuntimeException;

final class ConnectionFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly string $projectRoot,
    ) {}

    public function pdo(): PDO
    {
        $connection = strtolower($this->config->dbConnection);

        try {
            return match ($connection) {
                'sqlite' => $this->sqlite(),
                'mysql' => $this->mysql(),
                default => throw new RuntimeException("Unsupported DB_CONNECTION '{$this->config->dbConnection}'"),
            };
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function sqlite(): PDO
    {
        $db = trim($this->config->dbDatabase);
        if ($db === '') {
            throw new RuntimeException('DB_DATABASE is required for sqlite');
        }

        $path = $this->absolutePath($db);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, $this->pdoOptions());

        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }

    private function mysql(): PDO
    {
        $host = $this->config->dbHost;
        $port = $this->config->dbPort ?? 3306;
        $db = trim($this->config->dbDatabase);
        $user = $this->config->dbUsername;
        $pass = $this->config->dbPassword;

        if ($host === null || $host === '') {
            throw new RuntimeException('DB_HOST is required for mysql');
        }
        if ($db === '') {
            throw new RuntimeException('DB_DATABASE is required for mysql');
        }
        if ($user === null || $user === '') {
            throw new RuntimeException('DB_USERNAME is required for mysql');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        return new PDO($dsn, $user, $pass ?? '', $this->pdoOptions());
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

    private function absolutePath(string $maybeRelative): string
    {
        if ($maybeRelative === '') {
            return $this->projectRoot;
        }

        if ($maybeRelative[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $maybeRelative) === 1) {
            return $maybeRelative;
        }

        return rtrim($this->projectRoot, '/') . '/' . ltrim($maybeRelative, '/');
    }
}
