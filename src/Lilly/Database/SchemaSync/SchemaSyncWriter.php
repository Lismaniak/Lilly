<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

use RuntimeException;

final class SchemaSyncWriter
{
    public function writeCreateMigration(string $pendingDir, string $domain, string $tableName, array $def): string
    {
        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_create_{$tableName}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->createTableMigrationStub($domain, $tableName, $def));
        return $file;
    }

    /**
     * @param array{
     *   drops:list<string>,
     *   renames:list<array{from:string,to:string}>,
     *   adds:list<array<string,mixed>>
     * } $ops
     */
    public function writeUpdateMigration(string $pendingDir, string $domain, string $tableName, array $ops): string
    {
        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_update_{$tableName}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->updateTableMigrationStub($domain, $tableName, $ops));
        return $file;
    }

    private function createTableMigrationStub(string $domain, string $tableName, array $def): string
    {
        $ns = "Domains\\{$domain}\\Database\\Migrations";

        $columns = $def['columns'] ?? null;
        if (!is_array($columns) || $columns === []) {
            throw new RuntimeException("Create stub missing columns for table '{$tableName}'");
        }

        $foreignKeys = $def['foreign_keys'] ?? [];
        if (!is_array($foreignKeys)) {
            $foreignKeys = [];
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
        $lines[] = "";
        $lines[] = "return function (PDO \$pdo): void {";
        $lines[] = "    \$schema = new Schema(\$pdo);";
        $lines[] = "";
        $lines[] = "    \$schema->create('" . $this->escapeSingleQuoted($tableName) . "', function (Blueprint \$t): void {";

        foreach ($columns as $c) {
            if (!is_array($c) || !isset($c['name'], $c['type'])) {
                continue;
            }
            $lines[] = $this->emitColumnLine($c);
        }

        $fkLines = $this->emitForeignKeyLines($foreignKeys);
        if ($fkLines !== []) {
            $lines[] = "";
            foreach ($fkLines as $l) {
                $lines[] = $l;
            }
        }

        $lines[] = "    });";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    /**
     * @param array{
     *   drops:list<string>,
     *   renames:list<array{from:string,to:string}>,
     *   adds:list<array<string,mixed>>
     * } $ops
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
        $lines[] = "    \$schema->table('" . $this->escapeSingleQuoted($tableName) . "', function (Blueprint \$t): void {";

        foreach (($ops['drops'] ?? []) as $name) {
            $name = $this->escapeSingleQuoted((string) $name);
            if ($name !== '') {
                $lines[] = "        // DROP inferred: removed from define()";
                $lines[] = "        \$t->drop('{$name}');";
                $lines[] = "";
            }
        }

        if (($ops['drops'] ?? []) !== [] && end($lines) === "") {
            array_pop($lines);
        }

        foreach (($ops['renames'] ?? []) as $r) {
            $from = $this->escapeSingleQuoted((string) ($r['from'] ?? ''));
            $to = $this->escapeSingleQuoted((string) ($r['to'] ?? ''));
            if ($from !== '' && $to !== '') {
                $lines[] = "        \$t->rename('{$from}', '{$to}');";
            }
        }

        if (($ops['renames'] ?? []) !== [] && ($ops['adds'] ?? []) !== []) {
            $lines[] = "";
        }

        foreach (($ops['adds'] ?? []) as $c) {
            if (!is_array($c) || !isset($c['name'], $c['type'])) {
                continue;
            }
            $lines[] = $this->emitColumnLine($c);
        }

        $lines[] = "    });";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    /**
     * @param array<int, mixed> $foreignKeys
     * @return list<string>
     */
    private function emitForeignKeyLines(array $foreignKeys): array
    {
        $out = [];

        foreach ($foreignKeys as $fk) {
            if (!is_array($fk)) {
                continue;
            }

            $column = $this->escapeSingleQuoted(trim((string) ($fk['column'] ?? '')));
            $references = $this->escapeSingleQuoted(trim((string) ($fk['references'] ?? '')));
            $on = $this->escapeSingleQuoted(trim((string) ($fk['on'] ?? '')));

            if ($column === '' || $references === '' || $on === '') {
                continue;
            }

            $onDelete = $fk['onDelete'] ?? null;
            if ($onDelete !== null) {
                $onDelete = $this->escapeSingleQuoted(trim((string) $onDelete));
                if ($onDelete === '') {
                    $onDelete = null;
                }
            }

            if ($onDelete === null) {
                $out[] = "        \$t->foreignKey('{$column}', '{$references}', '{$on}');";
                continue;
            }

            $out[] = "        \$t->foreignKey('{$column}', '{$references}', '{$on}', '{$onDelete}');";
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $c
     */
    private function emitColumnLine(array $c): string
    {
        $name = $this->escapeSingleQuoted(trim((string) $c['name']));
        $type = trim((string) $c['type']);

        if ($name === '' || $type === '') {
            return "        // skipped invalid column";
        }

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

        throw new RuntimeException("Unsupported column type '{$type}' for '{$name}' in migration stub");
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
}
