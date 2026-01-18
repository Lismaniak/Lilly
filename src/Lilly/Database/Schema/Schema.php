<?php
declare(strict_types=1);

namespace Lilly\Database\Schema;

use PDO;
use RuntimeException;

final class Schema
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * @param callable(Blueprint): void $callback
     */
    public function create(string $table, callable $callback): void
    {
        $t = new Blueprint($table, 'create');
        $callback($t);
        $t->ensureHasColumnsForCreate();

        $driver = $this->driver();

        $sql = match ($driver) {
            'sqlite' => $this->compileCreateSqlite($t),
            'mysql' => $this->compileCreateMysql($t),
            default => throw new RuntimeException("Unsupported driver '{$driver}'"),
        };

        $this->pdo->exec($sql);

        $this->applyUniqueIndexes($table, $t, $driver);
    }

    /**
     * MVP alter support: add columns only.
     *
     * @param callable(Blueprint): void $callback
     */
    public function table(string $table, callable $callback): void
    {
        $t = new Blueprint($table, 'alter');
        $callback($t);

        $columns = $t->columns();
        if ($columns === []) {
            return;
        }

        $driver = $this->driver();

        foreach ($columns as $c) {
            $sql = match ($driver) {
                'sqlite' => $this->compileAddColumnSqlite($table, $c),
                'mysql' => $this->compileAddColumnMysql($table, $c),
                default => throw new RuntimeException("Unsupported driver '{$driver}'"),
            };

            $this->pdo->exec($sql);
        }

        $this->applyUniqueIndexes($table, $t, $driver);
    }

    public function dropIfExists(string $table): void
    {
        $driver = $this->driver();

        $sql = match ($driver) {
            'sqlite' => "DROP TABLE IF EXISTS " . $this->qiSqlite($table),
            'mysql' => "DROP TABLE IF EXISTS " . $this->qiMysql($table),
            default => throw new RuntimeException("Unsupported driver '{$driver}'"),
        };

        $this->pdo->exec($sql);
    }

    private function driver(): string
    {
        return strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    private function compileCreateSqlite(Blueprint $t): string
    {
        $parts = [];

        foreach ($t->columns() as $c) {
            if ($c->type === 'id') {
                $parts[] = $this->qiSqlite($c->name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
                continue;
            }

            $col = $this->qiSqlite($c->name) . ' ' . $this->mapTypeSqlite($c->type);

            if (!$c->nullable) {
                $col .= ' NOT NULL';
            }

            if ($c->default !== null) {
                $col .= ' DEFAULT ' . $this->literalSqlite($c->default);
            }

            $parts[] = $col;
        }

        $cols = implode(",\n            ", $parts);

        return "CREATE TABLE IF NOT EXISTS " . $this->qiSqlite($t->table()) . " (\n            {$cols}\n        )";
    }

    private function compileCreateMysql(Blueprint $t): string
    {
        $parts = [];

        foreach ($t->columns() as $c) {
            if ($c->type === 'id') {
                $parts[] = $this->qiMysql($c->name) . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
                continue;
            }

            $col = $this->qiMysql($c->name) . ' ' . $this->mapTypeMysql($c->type);
            $col .= $c->nullable ? ' NULL' : ' NOT NULL';

            if ($c->default !== null) {
                $col .= ' DEFAULT ' . $this->literalMysql($c->default);
            }

            $parts[] = $col;
        }

        $cols = implode(",\n            ", $parts);

        return "CREATE TABLE IF NOT EXISTS " . $this->qiMysql($t->table()) . " (\n            {$cols}\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    private function compileAddColumnSqlite(string $table, Column $c): string
    {
        if ($c->type === 'id') {
            throw new RuntimeException("Cannot add id() via alter for sqlite table '{$table}'");
        }

        $col = $this->qiSqlite($c->name) . ' ' . $this->mapTypeSqlite($c->type);

        if (!$c->nullable) {
            if ($c->default === null) {
                throw new RuntimeException("SQLite cannot add NOT NULL column '{$c->name}' without a default");
            }
            $col .= ' NOT NULL';
        }

        if ($c->default !== null) {
            $col .= ' DEFAULT ' . $this->literalSqlite($c->default);
        }

        return "ALTER TABLE " . $this->qiSqlite($table) . " ADD COLUMN {$col}";
    }

    private function compileAddColumnMysql(string $table, Column $c): string
    {
        if ($c->type === 'id') {
            throw new RuntimeException("Cannot add id() via alter for mysql table '{$table}'");
        }

        $col = $this->qiMysql($c->name) . ' ' . $this->mapTypeMysql($c->type);
        $col .= $c->nullable ? ' NULL' : ' NOT NULL';

        if ($c->default !== null) {
            $col .= ' DEFAULT ' . $this->literalMysql($c->default);
        }

        return "ALTER TABLE " . $this->qiMysql($table) . " ADD COLUMN {$col}";
    }

    private function applyUniqueIndexes(string $table, Blueprint $t, string $driver): void
    {
        foreach ($t->columns() as $c) {
            if (!$c->unique) {
                continue;
            }

            $indexName = $this->uniqueIndexName($table, $c->name);

            $sql = match ($driver) {
                'sqlite' => "CREATE UNIQUE INDEX IF NOT EXISTS " . $this->qiSqlite($indexName) .
                    " ON " . $this->qiSqlite($table) . " (" . $this->qiSqlite($c->name) . ")",
                'mysql' => "ALTER TABLE " . $this->qiMysql($table) .
                    " ADD UNIQUE KEY " . $this->qiMysql($indexName) .
                    " (" . $this->qiMysql($c->name) . ")",
                default => null,
            };

            if ($sql !== null) {
                $this->pdo->exec($sql);
            }
        }
    }

    private function uniqueIndexName(string $table, string $col): string
    {
        return "{$table}_{$col}_unique";
    }

    private function mapTypeSqlite(string $type): string
    {
        if (str_starts_with($type, 'string:')) {
            return 'TEXT';
        }

        return match ($type) {
            'text' => 'TEXT',
            'int' => 'INTEGER',
            'bool' => 'INTEGER',
            'timestamp' => 'TEXT',
            default => throw new RuntimeException("Unsupported column type '{$type}' for sqlite"),
        };
    }

    private function mapTypeMysql(string $type): string
    {
        if (str_starts_with($type, 'string:')) {
            $len = (int) substr($type, strlen('string:'));
            $len = $len > 0 ? $len : 255;
            return "VARCHAR({$len})";
        }

        return match ($type) {
            'text' => 'TEXT',
            'int' => 'INT',
            'bool' => 'TINYINT(1)',
            'timestamp' => 'DATETIME',
            default => throw new RuntimeException("Unsupported column type '{$type}' for mysql"),
        };
    }

    private function qiSqlite(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private function qiMysql(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function literalSqlite(mixed $v): string
    {
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        $s = str_replace("'", "''", (string) $v);
        return "'" . $s . "'";
    }

    private function literalMysql(mixed $v): string
    {
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        $s = str_replace("'", "''", (string) $v);
        return "'" . $s . "'";
    }
}
