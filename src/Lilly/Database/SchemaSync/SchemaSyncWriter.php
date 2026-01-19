<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

use RuntimeException;

final class SchemaSyncWriter
{
    public function writeCreateMigration(
        string $pendingDir,
        string $domain,
        string $tableName,
        array $def,
        ?string $stamp = null
    ): string
    {
        $stamp = $stamp ?? gmdate('Y_m_d_His');
        $descriptor = $this->buildCreateDescriptor($tableName, $def);
        $file = "{$stamp}_{$descriptor}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->createTableMigrationStub($domain, $tableName, $def));

        return $file;
    }

    /**
     * @param array{
     *   drops:list<string>,
     *   renames:list<array{from:string,to:string}>,
     *   adds:list<array<string,mixed>>,
     *   changes:list<array{name:string,type:?string,nullable:?bool,default:mixed,unique:?bool}>,
     *   foreign_keys_adds?:list<array{column:string,references:string,on:string,onDelete?:string|null}>,
     *   foreign_keys_drops?:list<string>
     * } $ops
     */
    public function writeUpdateMigration(string $pendingDir, string $domain, string $tableName, array $ops): string
    {
        $stamp = gmdate('Y_m_d_His');
        $descriptor = $this->buildUpdateDescriptor($tableName, $ops);
        $file = "{$stamp}_{$descriptor}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->updateTableMigrationStub($domain, $tableName, $ops));

        return $file;
    }

    public function writeDropMigration(string $pendingDir, string $domain, string $tableName): string
    {
        $stamp = gmdate('Y_m_d_His');
        $descriptor = $this->buildDropDescriptor($tableName);
        $file = "{$stamp}_{$descriptor}.php";
        $path = "{$pendingDir}/{$file}";

        file_put_contents($path, $this->dropTableMigrationStub($domain, $tableName));

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
     *   adds:list<array<string,mixed>>,
     *   changes:list<array{name:string,type:?string,nullable:?bool,default:mixed,unique:?bool}>,
     *   foreign_keys_adds?:list<array{column:string,references:string,on:string,onDelete?:string|null}>,
     *   foreign_keys_drops?:list<string>
     * } $ops
     */
    private function updateTableMigrationStub(string $domain, string $tableName, array $ops): string
    {
        $ns = "Domains\\{$domain}\\Database\\Migrations";

        $drops = $ops['drops'] ?? [];
        $renames = $ops['renames'] ?? [];
        $adds = $ops['adds'] ?? [];
        $changes = $ops['changes'] ?? [];
        if (!is_array($changes)) {
            $changes = [];
        }

        $fkAdds = $ops['foreign_keys_adds'] ?? [];
        if (!is_array($fkAdds)) {
            $fkAdds = [];
        }
        $fkDrops = $ops['foreign_keys_drops'] ?? [];
        if (!is_array($fkDrops)) {
            $fkDrops = [];
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
        $lines[] = "    \$schema->table('" . $this->escapeSingleQuoted($tableName) . "', function (Blueprint \$t): void {";

        $this->emitDropForeignKeyLines($lines, $tableName, $fkDrops);
        $this->emitDropLines($lines, $drops);
        $this->emitRenameLines($lines, $renames);

        $hadFkDrops = $fkDrops !== [];
        $hadDrops = $drops !== [];
        $hadRenames = $renames !== [];
        $hadAdds = $adds !== [];
        $hadChanges = $changes !== [];
        $hadFkAdds = $fkAdds !== [];

        if (($hadFkDrops || $hadDrops || $hadRenames) && ($hadChanges || $hadAdds || $hadFkAdds)) {
            $this->ensureBlankLine($lines);
        }

        $this->emitChangeLines($lines, $changes);

        if ($hadChanges && ($hadAdds || $hadFkAdds)) {
            $this->ensureBlankLine($lines);
        }

        $this->emitAddColumnLines($lines, $adds);

        if ($hadAdds && $hadFkAdds) {
            $this->ensureBlankLine($lines);
        }

        $this->emitForeignKeyAddLines($lines, $fkAdds);

        $lines[] = "    });";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    private function dropTableMigrationStub(string $domain, string $tableName): string
    {
        $ns = "Domains\\{$domain}\\Database\\Migrations";

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "";
        $lines[] = "namespace {$ns};";
        $lines[] = "";
        $lines[] = "use PDO;";
        $lines[] = "use Lilly\\Database\\Schema\\Schema;";
        $lines[] = "";
        $lines[] = "return function (PDO \$pdo): void {";
        $lines[] = "    \$schema = new Schema(\$pdo);";
        $lines[] = "";
        $lines[] = "    \$schema->dropIfExists('" . $this->escapeSingleQuoted($tableName) . "');";
        $lines[] = "};";
        $lines[] = "";

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $drops
     */
    private function emitDropLines(array &$lines, array $drops): void
    {
        foreach ($drops as $name) {
            $name = $this->escapeSingleQuoted((string) $name);
            if ($name === '') {
                continue;
            }

            $lines[] = "        // DROP inferred: removed from define()";
            $lines[] = "        \$t->drop('{$name}');";
            $lines[] = "";
        }

        if ($drops !== [] && end($lines) === "") {
            array_pop($lines);
        }
    }

    /**
     * @param list<string> $drops
     */
    private function emitDropForeignKeyLines(array &$lines, string $tableName, array $drops): void
    {
        foreach ($drops as $column) {
            $column = $this->escapeSingleQuoted((string) $column);
            if ($column === '') {
                continue;
            }

            $fkName = $this->escapeSingleQuoted($this->foreignKeyName($tableName, $column));

            $lines[] = "        // DROP FK inferred: removed from foreignKeys()";
            $lines[] = "        \$t->dropForeignKey('{$fkName}');";
            $lines[] = "";
        }

        if ($drops !== [] && end($lines) === "") {
            array_pop($lines);
        }
    }

    /**
     * @param list<array{from:string,to:string}> $renames
     */
    private function emitRenameLines(array &$lines, array $renames): void
    {
        $emitted = false;

        foreach ($renames as $r) {
            if (!is_array($r)) {
                continue;
            }

            $from = $this->escapeSingleQuoted((string) ($r['from'] ?? ''));
            $to = $this->escapeSingleQuoted((string) ($r['to'] ?? ''));

            if ($from === '' || $to === '') {
                continue;
            }

            $lines[] = "        \$t->rename('{$from}', '{$to}');";
            $emitted = true;
        }

        if ($emitted) {
            return;
        }
    }

    /**
     * @param list<array{name:string,type:?string,nullable:?bool,default:mixed,unique:?bool}> $changes
     */
    private function emitChangeLines(array &$lines, array $changes): void
    {
        foreach ($changes as $ch) {
            if (!is_array($ch) || !isset($ch['name'])) {
                continue;
            }

            $name = $this->escapeSingleQuoted(trim((string) $ch['name']));
            if ($name === '') {
                continue;
            }

            $expr = "\$t->change('{$name}')";

            $type = $ch['type'] ?? null;
            if (is_string($type)) {
                $type = trim($type);
                if ($type !== '') {
                    $expr .= "->type('" . $this->escapeSingleQuoted($type) . "')";
                }
            }

            if (array_key_exists('nullable', $ch) && $ch['nullable'] !== null) {
                $expr .= $ch['nullable'] ? "->nullable(true)" : "->nullable(false)";
            }

            if (array_key_exists('default', $ch) && $ch['default'] !== '__KEEP__') {
                $expr .= "->default(" . $this->exportPhpValue($ch['default']) . ")";
            }

            if (array_key_exists('unique', $ch) && $ch['unique'] !== null) {
                $expr .= $ch['unique'] ? "->unique(true)" : "->unique(false)";
            }

            $lines[] = "        {$expr};";
        }
    }

    /**
     * @param list<array<string,mixed>> $adds
     */
    private function emitAddColumnLines(array &$lines, array $adds): void
    {
        foreach ($adds as $c) {
            if (!is_array($c) || !isset($c['name'], $c['type'])) {
                continue;
            }
            $lines[] = $this->emitColumnLine($c);
        }
    }

    /**
     * @param list<array{column:string,references:string,on:string,onDelete?:string|null}> $fkAdds
     */
    private function emitForeignKeyAddLines(array &$lines, array $fkAdds): void
    {
        if ($fkAdds === []) {
            return;
        }

        $fkLines = $this->emitForeignKeyLines($fkAdds);
        if ($fkLines === []) {
            return;
        }

        foreach ($fkLines as $l) {
            $lines[] = $l;
        }
    }

    private function ensureBlankLine(array &$lines): void
    {
        if ($lines === []) {
            return;
        }
        if (end($lines) !== "") {
            $lines[] = "";
        }
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
        if ($type === 'datetime') {
            return "\$t->datetime('{$name}')";
        }
        if ($type === 'date') {
            return "\$t->date('{$name}')";
        }
        if ($type === 'ubigint') {
            return "\$t->unsignedBigInteger('{$name}')";
        }
        if ($type === 'bigint') {
            return "\$t->bigInteger('{$name}')";
        }
        if ($type === 'uuid') {
            return "\$t->uuid('{$name}')";
        }
        if ($type === 'json') {
            return "\$t->json('{$name}')";
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

    private function foreignKeyName(string $table, string $col): string
    {
        return "{$table}_{$col}_fk";
    }

    private function buildCreateDescriptor(string $tableName, array $def): string
    {
        $parts = ['create', $tableName, 'table'];

        $columns = $def['columns'] ?? [];
        if (is_array($columns)) {
            $names = [];
            foreach ($columns as $column) {
                if (!is_array($column) || !isset($column['name'])) {
                    continue;
                }
                $names[] = (string) $column['name'];
            }
            $this->appendNameParts($parts, $names, 'with');
        }

        return $this->buildDescriptor($parts);
    }

    private function buildDropDescriptor(string $tableName): string
    {
        $parts = ['drop', $tableName, 'table'];

        return $this->buildDescriptor($parts);
    }

    /**
     * @param array{
     *   drops:list<string>,
     *   renames:list<array{from:string,to:string}>,
     *   adds:list<array<string,mixed>>,
     *   changes:list<array{name:string,type:?string,nullable:?bool,default:mixed,unique:?bool}>,
     *   foreign_keys_adds?:list<array{column:string,references:string,on:string,onDelete?:string|null}>,
     *   foreign_keys_drops?:list<string>
     * } $ops
     */
    private function buildUpdateDescriptor(string $tableName, array $ops): string
    {
        $parts = ['update', $tableName];

        $addNames = [];
        foreach (($ops['adds'] ?? []) as $add) {
            if (!is_array($add) || !isset($add['name'])) {
                continue;
            }
            $addNames[] = (string) $add['name'];
        }
        $this->appendNameParts($parts, $addNames, 'add');

        $dropNames = [];
        foreach (($ops['drops'] ?? []) as $drop) {
            $dropNames[] = (string) $drop;
        }
        $this->appendNameParts($parts, $dropNames, 'drop');

        $renameParts = [];
        foreach (($ops['renames'] ?? []) as $rename) {
            if (!is_array($rename)) {
                continue;
            }
            $from = (string) ($rename['from'] ?? '');
            $to = (string) ($rename['to'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }
            $renameParts[] = "{$from}_to_{$to}";
        }
        $this->appendNameParts($parts, $renameParts, 'rename', 2);

        $changeNames = [];
        foreach (($ops['changes'] ?? []) as $change) {
            if (!is_array($change) || !isset($change['name'])) {
                continue;
            }
            $changeNames[] = (string) $change['name'];
        }
        $this->appendNameParts($parts, $changeNames, 'change');

        $fkAddNames = [];
        foreach (($ops['foreign_keys_adds'] ?? []) as $fkAdd) {
            if (!is_array($fkAdd) || !isset($fkAdd['column'])) {
                continue;
            }
            $fkAddNames[] = (string) $fkAdd['column'];
        }
        $this->appendNameParts($parts, $fkAddNames, 'add_fk');

        $fkDropNames = [];
        foreach (($ops['foreign_keys_drops'] ?? []) as $fkDrop) {
            $fkDropNames[] = (string) $fkDrop;
        }
        $this->appendNameParts($parts, $fkDropNames, 'drop_fk');

        return $this->buildDescriptor($parts);
    }

    /**
     * @param list<string> $names
     */
    private function appendNameParts(array &$parts, array $names, string $label, int $max = 3): void
    {
        $clean = [];
        foreach ($names as $name) {
            $value = $this->sanitizeDescriptorPart((string) $name);
            if ($value === '') {
                continue;
            }
            $clean[] = $value;
        }

        if ($clean === []) {
            return;
        }

        $parts[] = $label;
        foreach (array_slice($clean, 0, $max) as $name) {
            $parts[] = $name;
        }

        $extra = count($clean) - $max;
        if ($extra > 0) {
            $parts[] = "and_{$extra}_more";
        }
    }

    /**
     * @param list<string> $parts
     */
    private function buildDescriptor(array $parts): string
    {
        $cleanParts = [];
        foreach ($parts as $part) {
            $value = $this->sanitizeDescriptorPart((string) $part);
            if ($value === '') {
                continue;
            }
            $cleanParts[] = $value;
        }

        $descriptor = trim(implode('_', $cleanParts), '_');
        if ($descriptor === '') {
            $descriptor = 'migration';
        }

        if (strlen($descriptor) > 160) {
            $descriptor = substr($descriptor, 0, 160);
            $descriptor = rtrim($descriptor, '_');
        }

        return $descriptor;
    }

    private function sanitizeDescriptorPart(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }
}
