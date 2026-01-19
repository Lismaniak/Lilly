<?php
declare(strict_types=1);

namespace Lilly\Database\Schema;

use RuntimeException;

final class Blueprint
{
    /**
     * @var list<Column>
     */
    private array $columns = [];

    /**
     * @var list<string>
     */
    private array $dropColumns = [];

    /**
     * @var list<array{from: string, to: string}>
     */
    private array $renameColumns = [];

    /**
     * @var list<ColumnChange>
     */
    private array $changeColumns = [];

    /**
     * @var list<ForeignKey>
     */
    private array $foreignKeys = [];

    /**
     * @var list<string>
     */
    private array $dropForeignKeys = [];

    /**
     * Column rename hints: newName => list<oldName>
     *
     * @var array<string, list<string>>
     */
    private array $was = [];

    public function __construct(
        private readonly string $table,
        private readonly string $mode = 'create' // create|alter
    ) {}

    public function table(): string
    {
        return $this->table;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @return list<Column>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<string>
     */
    public function drops(): array
    {
        return $this->dropColumns;
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    public function renames(): array
    {
        return $this->renameColumns;
    }

    /**
     * @return list<ColumnChange>
     */
    public function changes(): array
    {
        return $this->changeColumns;
    }

    /**
     * @return list<string>
     */
    public function dropForeignKeys(): array
    {
        return $this->dropForeignKeys;
    }

    public function id(string $name = 'id'): Column
    {
        $col = new Column(
            $name,
            'id',
            nullable: false,
            unique: false,
            primary: true,
            autoIncrement: true
        );

        return $this->addColumn($col);
    }

    public function unsignedBigInteger(string $name): Column
    {
        $col = new Column(
            $name,
            'ubigint',
            nullable: false,
            unique: false,
            primary: false,
            autoIncrement: false
        );

        return $this->addColumn($col);
    }

    public function string(string $name, int $length = 255): Column
    {
        $col = new Column($name, "string:{$length}");
        return $this->addColumn($col);
    }

    public function text(string $name): Column
    {
        $col = new Column($name, 'text');
        return $this->addColumn($col);
    }

    public function int(string $name): Column
    {
        $col = new Column($name, 'int');
        return $this->addColumn($col);
    }

    public function boolean(string $name): Column
    {
        $col = new Column($name, 'bool');
        return $this->addColumn($col);
    }

    public function timestamp(string $name): Column
    {
        $col = new Column($name, 'timestamp');
        return $this->addColumn($col);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at')->nullable();
    }

    public function drop(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $this->dropColumns[] = $name;
    }

    public function rename(string $from, string $to): void
    {
        $from = trim($from);
        $to = trim($to);

        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        $this->renameColumns[] = ['from' => $from, 'to' => $to];
    }

    public function change(string $name): ColumnChange
    {
        $name = trim($name);
        $c = new ColumnChange($name !== '' ? $name : '__invalid__');
        $this->changeColumns[] = $c;
        return $c;
    }

    public function dropForeignKey(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $this->dropForeignKeys[] = $name;
    }

    /**
     * Add a foreign key definition (loose, does not validate column existence).
     * Prefer foreignKey() for strict behavior.
     */
    public function foreign(string $column): ForeignKey
    {
        $column = trim($column);
        if ($column === '') {
            throw new RuntimeException("Foreign key column may not be empty on table '{$this->table}'");
        }

        $fk = new ForeignKey($column);
        $this->foreignKeys[] = $fk;
        return $fk;
    }

    /**
     * Strict helper: requires the FK column to exist before adding the FK.
     *
     * Typical usage:
     * $t->unsignedBigInteger('user_id');
     * $t->foreignKey('user_id', 'id', 'users', 'cascade');
     */
    public function foreignKey(
        string $column,
        string $references,
        string $on,
        string $onDelete = 'restrict'
    ): ForeignKey {
        $column = trim($column);
        $references = trim($references);
        $on = trim($on);

        if ($column === '' || $references === '' || $on === '') {
            throw new RuntimeException("foreignKey(): invalid definition on table '{$this->table}'");
        }

        if (!$this->hasColumn($column)) {
            throw new RuntimeException(
                "foreignKey(): column '{$column}' must be defined before adding a foreign key on table '{$this->table}'"
            );
        }

        $fk = $this->foreign($column);
        $fk->references($on, $references)->onDelete($onDelete);

        return $fk;
    }

    /**
     * @return list<ForeignKey>
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return array<string, list<string>> newName => legacy names
     */
    public function was(): array
    {
        return $this->was;
    }

    /**
     * @param list<string> $from
     */
    public function registerWas(string $to, array $from): void
    {
        $to = trim($to);
        if ($to === '') {
            throw new RuntimeException('was(): target column may not be empty');
        }

        $clean = [];
        foreach ($from as $name) {
            if (!is_string($name)) {
                throw new RuntimeException("was(): invalid legacy column for '{$to}'");
            }

            $name = trim($name);
            if ($name === '' || $name === $to) {
                continue;
            }

            $clean[] = $name;
        }

        $clean = array_values(array_unique($clean));
        if ($clean === []) {
            return;
        }

        $existing = $this->was[$to] ?? [];
        $merged = array_values(array_unique(array_merge($existing, $clean)));
        $this->was[$to] = $merged;
    }

    public function ensureHasColumnsForCreate(): void
    {
        if ($this->columns === []) {
            throw new RuntimeException("Table '{$this->table}' has no columns");
        }
    }

    private function addColumn(Column $col): Column
    {
        $col->bindBlueprint($this);
        $this->columns[] = $col;
        return $col;
    }

    private function hasColumn(string $name): bool
    {
        foreach ($this->columns as $col) {
            if ($col->name === $name) {
                return true;
            }
        }

        return false;
    }
}
