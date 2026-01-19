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

        if ($t->foreignKeys() !== []) {
            $this->applyForeignKeys($table, $t, $driver, 'create');
        }
    }

    /**
     * MVP alter support:
     * - add columns
     * - drop columns
     * - rename columns
     * - change type/nullable/default
     * - change unique (via ColumnChange->unique())
     * - add foreign keys
     * - drop foreign keys (NEW, mysql only)
     *
     * @param callable(Blueprint): void $callback
     */
    public function table(string $table, callable $callback): void
    {
        $t = new Blueprint($table, 'alter');
        $callback($t);

        $adds = $t->columns();
        $drops = $t->drops();
        $renames = $t->renames();
        $changes = $t->changes();
        $fks = $t->foreignKeys();

        $dropFks = method_exists($t, 'dropForeignKeys') ? $t->dropForeignKeys() : [];

        if ($adds === [] && $drops === [] && $renames === [] && $changes === [] && $fks === [] && $dropFks === []) {
            return;
        }

        $driver = $this->driver();

        if ($driver === 'sqlite') {
            if ($fks !== [] || $dropFks !== []) {
                throw new RuntimeException('SQLite foreign keys are not supported yet in Lilly migrations. Use MySQL for now.');
            }

            $needsRebuild = ($drops !== []) || ($renames !== []) || ($changes !== []);
            if ($needsRebuild) {
                $this->rebuildSqliteTable($table, $t);
                return;
            }

            foreach ($adds as $c) {
                $this->pdo->exec($this->compileAddColumnSqlite($table, $c));
            }

            $this->applyUniqueIndexes($table, $t, 'sqlite');
            $this->applyUniqueIndexChanges($table, $changes, 'sqlite');
            return;
        }

        if ($driver === 'mysql') {
            foreach ($dropFks as $fkName) {
                $fkName = trim((string) $fkName);
                if ($fkName === '') {
                    continue;
                }

                $sql = "ALTER TABLE " . $this->qiMysql($table) .
                    " DROP FOREIGN KEY " . $this->qiMysql($fkName);

                $this->pdo->exec($sql);
            }

            foreach ($drops as $name) {
                $sql = "ALTER TABLE " . $this->qiMysql($table) . " DROP COLUMN " . $this->qiMysql($name);
                $this->pdo->exec($sql);
            }

            foreach ($renames as $r) {
                $sql = "ALTER TABLE " . $this->qiMysql($table) .
                    " RENAME COLUMN " . $this->qiMysql($r['from']) .
                    " TO " . $this->qiMysql($r['to']);
                $this->pdo->exec($sql);
            }

            foreach ($changes as $ch) {
                if ($ch->name === '__invalid__') {
                    continue;
                }

                if (!$this->hasColumnDefinitionChange($ch)) {
                    continue;
                }

                $sql = "ALTER TABLE " . $this->qiMysql($table) .
                    " MODIFY COLUMN " . $this->qiMysql($ch->name) . " " .
                    $this->compileMysqlChangedColumnDefinition($table, $ch);

                $this->pdo->exec($sql);
            }

            foreach ($adds as $c) {
                $this->pdo->exec($this->compileAddColumnMysql($table, $c));
            }

            $this->applyUniqueIndexes($table, $t, 'mysql');
            $this->applyUniqueIndexChanges($table, $changes, 'mysql');

            if ($fks !== []) {
                $this->applyForeignKeys($table, $t, 'mysql', 'alter');
            }

            return;
        }

        throw new RuntimeException("Unsupported driver '{$driver}'");
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
                if ($c->primary) {
                    $parts[] = $this->qiMysql($c->name) . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
                } else {
                    $col = $this->qiMysql($c->name) . ' BIGINT UNSIGNED';
                    $col .= $c->nullable ? ' NULL' : ' NOT NULL';

                    if ($c->default !== null) {
                        $col .= ' DEFAULT ' . $this->literalMysql($c->default);
                    }

                    $parts[] = $col;
                }
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

        return "CREATE TABLE IF NOT EXISTS " . $this->qiMysql($t->table()) .
            " (\n            {$cols}\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
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

    /**
     * @param list<ColumnChange> $changes
     */
    private function applyUniqueIndexChanges(string $table, array $changes, string $driver): void
    {
        foreach ($changes as $ch) {
            if ($ch->name === '__invalid__') {
                continue;
            }

            if (!property_exists($ch, 'unique')) {
                continue;
            }

            if ($ch->unique === null) {
                continue;
            }

            $indexName = $this->uniqueIndexName($table, $ch->name);

            if ($driver === 'mysql') {
                if ($ch->unique === true) {
                    $sql = "ALTER TABLE " . $this->qiMysql($table) .
                        " ADD UNIQUE KEY " . $this->qiMysql($indexName) .
                        " (" . $this->qiMysql($ch->name) . ")";
                    $this->pdo->exec($sql);
                    continue;
                }

                if ($this->mysqlIndexExists($table, $indexName)) {
                    $sql = "ALTER TABLE " . $this->qiMysql($table) .
                        " DROP INDEX " . $this->qiMysql($indexName);
                    $this->pdo->exec($sql);
                }

                continue;
            }

            if ($driver === 'sqlite') {
                if ($ch->unique === true) {
                    $sql = "CREATE UNIQUE INDEX IF NOT EXISTS " . $this->qiSqlite($indexName) .
                        " ON " . $this->qiSqlite($table) .
                        " (" . $this->qiSqlite($ch->name) . ")";
                    $this->pdo->exec($sql);
                    continue;
                }

                $sql = "DROP INDEX IF EXISTS " . $this->qiSqlite($indexName);
                $this->pdo->exec($sql);
            }
        }
    }

    private function applyForeignKeys(string $table, Blueprint $t, string $driver, string $mode): void
    {
        if ($driver !== 'mysql') {
            if ($driver === 'sqlite') {
                throw new RuntimeException("SQLite foreign keys are not supported yet ({$mode}). Use MySQL for now.");
            }
            throw new RuntimeException("Unsupported driver '{$driver}'");
        }

        foreach ($t->foreignKeys() as $fk) {
            if ($fk->column === '__invalid__' || $fk->refTable === null || $fk->refColumn === null) {
                continue;
            }

            $name = $fk->name ?? $this->foreignKeyName($table, $fk->column);

            $sql = "ALTER TABLE " . $this->qiMysql($table) .
                " ADD CONSTRAINT " . $this->qiMysql($name) .
                " FOREIGN KEY (" . $this->qiMysql($fk->column) . ")" .
                " REFERENCES " . $this->qiMysql($fk->refTable) .
                " (" . $this->qiMysql($fk->refColumn) . ")";

            if ($fk->onDelete !== null) {
                $sql .= " ON DELETE " . $this->fkActionMysql($fk->onDelete);
            }
            if ($fk->onUpdate !== null) {
                $sql .= " ON UPDATE " . $this->fkActionMysql($fk->onUpdate);
            }

            $this->pdo->exec($sql);
        }
    }

    private function uniqueIndexName(string $table, string $col): string
    {
        return "{$table}_{$col}_unique";
    }

    private function foreignKeyName(string $table, string $col): string
    {
        return "{$table}_{$col}_fk";
    }

    private function fkActionMysql(string $action): string
    {
        $a = strtolower(trim($action));

        return match ($a) {
            'cascade' => 'CASCADE',
            'restrict' => 'RESTRICT',
            'set-null' => 'SET NULL',
            'no-action' => 'NO ACTION',
            default => 'RESTRICT',
        };
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
            'ubigint' => 'INTEGER',
            default => throw new RuntimeException("Unsupported column type '{$type}' for sqlite"),
        };
    }

    private function mapTypeMysql(string $type): string
    {
        $type = trim($type);

        if ($type === 'string') {
            return 'VARCHAR(255)';
        }

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
            'ubigint' => 'BIGINT UNSIGNED',
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

    private function hasColumnDefinitionChange(ColumnChange $ch): bool
    {
        if ($ch->type !== null) {
            return true;
        }

        if ($ch->nullable !== null) {
            return true;
        }

        if ($ch->default !== '__KEEP__') {
            return true;
        }

        return false;
    }

    private function mysqlIndexExists(string $table, string $indexName): bool
    {
        $sql = "SHOW INDEX FROM " . $this->qiMysql($table) . " WHERE Key_name = :name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $indexName]);

        $row = $stmt->fetch();
        return $row !== false;
    }

    private function compileMysqlChangedColumnDefinition(string $table, ColumnChange $ch): string
    {
        if ($ch->type === null) {
            throw new RuntimeException("MySQL change() requires an explicit type() for '{$table}.{$ch->name}'");
        }

        $typeSql = $this->mapTypeMysql($ch->type);
        $nullableSql = ($ch->nullable === true) ? ' NULL' : ' NOT NULL';

        $defaultSql = '';
        if ($ch->default !== '__KEEP__') {
            if ($ch->default === null) {
                $defaultSql = ' DEFAULT NULL';
            } else {
                $defaultSql = ' DEFAULT ' . $this->literalMysql($ch->default);
            }
        }

        return $typeSql . $nullableSql . $defaultSql;
    }

    private function rebuildSqliteTable(string $table, Blueprint $t): void
    {
        $driver = $this->driver();
        if ($driver !== 'sqlite') {
            throw new RuntimeException('rebuildSqliteTable called for non-sqlite driver');
        }

        $this->pdo->exec('PRAGMA foreign_keys = OFF;');

        try {
            $existing = $this->sqliteTableInfo($table);
            if ($existing === []) {
                throw new RuntimeException("SQLite table '{$table}' does not exist");
            }

            $renameFromTo = [];
            foreach ($t->renames() as $r) {
                $renameFromTo[$r['from']] = $r['to'];
            }

            $dropSet = [];
            foreach ($t->drops() as $d) {
                $dropSet[$d] = true;
            }

            $changeMap = [];
            foreach ($t->changes() as $ch) {
                if ($ch->name === '__invalid__') {
                    continue;
                }
                $changeMap[$ch->name] = $ch;
            }

            $newCols = [];

            foreach ($existing as $col) {
                $oldName = $col['name'];

                if (isset($dropSet[$oldName])) {
                    continue;
                }

                $newName = $renameFromTo[$oldName] ?? $oldName;

                $type = $col['type'];
                $notnull = $col['notnull'];
                $dflt = $col['dflt_value'];
                $pk = $col['pk'];

                if (isset($changeMap[$oldName])) {
                    $ch = $changeMap[$oldName];

                    if ($ch->type !== null) {
                        $type = $this->mapTypeSqlite($ch->type);
                    }

                    if ($ch->nullable !== null) {
                        $notnull = $ch->nullable ? 0 : 1;
                    }

                    if ($ch->default !== '__KEEP__') {
                        $dflt = $ch->default;
                    }
                }

                $newCols[] = [
                    'name' => $newName,
                    'type' => $type !== '' ? $type : 'TEXT',
                    'notnull' => (int) $notnull,
                    'dflt_value' => $dflt,
                    'pk' => (int) $pk,
                    'from' => $oldName,
                ];
            }

            foreach ($t->columns() as $c) {
                $newCols[] = [
                    'name' => $c->name,
                    'type' => $this->mapTypeSqlite($c->type),
                    'notnull' => $c->nullable ? 0 : 1,
                    'dflt_value' => $c->default,
                    'pk' => $c->primary ? 1 : 0,
                    'from' => null,
                ];
            }

            $tmp = '__lilly_tmp_' . $table . '_' . substr(bin2hex(random_bytes(6)), 0, 12);

            $createSql = $this->compileCreateSqliteFromDefinition($tmp, $newCols);
            $this->pdo->exec($createSql);

            $toNames = [];
            $selectExpr = [];

            foreach ($newCols as $c) {
                if (!isset($c['from']) || $c['from'] === null) {
                    continue;
                }

                $toNames[] = $this->qiSqlite($c['name']);
                $selectExpr[] = $this->qiSqlite($c['from']);
            }

            if ($toNames !== []) {
                $insertSql =
                    "INSERT INTO " . $this->qiSqlite($tmp) .
                    " (" . implode(', ', $toNames) . ") " .
                    "SELECT " . implode(', ', $selectExpr) .
                    " FROM " . $this->qiSqlite($table);

                $this->pdo->exec($insertSql);
            }

            $this->pdo->exec("DROP TABLE " . $this->qiSqlite($table));
            $this->pdo->exec("ALTER TABLE " . $this->qiSqlite($tmp) . " RENAME TO " . $this->qiSqlite($table));

            $this->applyUniqueIndexes($table, $t, 'sqlite');
            $this->applyUniqueIndexChanges($table, $t->changes(), 'sqlite');
        } finally {
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        }
    }

    /**
     * @return list<array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int}>
     */
    private function sqliteTableInfo(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info(" . $this->qiSqlite($table) . ")");
        $rows = $stmt ? $stmt->fetchAll() : [];

        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r) || !isset($r['name'])) {
                continue;
            }

            $out[] = [
                'name' => (string) $r['name'],
                'type' => isset($r['type']) ? (string) $r['type'] : '',
                'notnull' => isset($r['notnull']) ? (int) $r['notnull'] : 0,
                'dflt_value' => $r['dflt_value'] ?? null,
                'pk' => isset($r['pk']) ? (int) $r['pk'] : 0,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int, from: string|null}> $cols
     */
    private function compileCreateSqliteFromDefinition(string $table, array $cols): string
    {
        $parts = [];

        foreach ($cols as $c) {
            $name = $this->qiSqlite($c['name']);
            $type = $c['type'] !== '' ? $c['type'] : 'TEXT';

            if ($c['pk'] === 1) {
                if ($c['name'] === 'id') {
                    $parts[] = $name . ' INTEGER PRIMARY KEY AUTOINCREMENT';
                } else {
                    $parts[] = $name . ' ' . $type . ' PRIMARY KEY';
                }
                continue;
            }

            $colSql = $name . ' ' . $type;

            if ((int) $c['notnull'] === 1) {
                $colSql .= ' NOT NULL';
            }

            if (array_key_exists('dflt_value', $c) && $c['dflt_value'] !== null) {
                $colSql .= ' DEFAULT ' . $this->literalSqlite($c['dflt_value']);
            }

            $parts[] = $colSql;
        }

        $colsSql = implode(",\n            ", $parts);

        return "CREATE TABLE IF NOT EXISTS " . $this->qiSqlite($table) . " (\n            {$colsSql}\n        )";
    }
}
