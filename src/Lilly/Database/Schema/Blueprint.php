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

    public function id(string $name = 'id'): Column
    {
        $col = new Column($name, 'id', nullable: false, unique: false, primary: true, autoIncrement: true);
        $this->columns[] = $col;
        return $col;
    }

    public function foreignId(string $name): Column
    {
        $c = new Column($name, 'id', nullable: false, unique: false, primary: false, autoIncrement: false);
        $this->columns[] = $c;
        return $c;
    }

    public function string(string $name, int $length = 255): Column
    {
        $col = new Column($name, "string:{$length}");
        $this->columns[] = $col;
        return $col;
    }

    public function text(string $name): Column
    {
        $col = new Column($name, 'text');
        $this->columns[] = $col;
        return $col;
    }

    public function int(string $name): Column
    {
        $col = new Column($name, 'int');
        $this->columns[] = $col;
        return $col;
    }

    public function boolean(string $name): Column
    {
        $col = new Column($name, 'bool');
        $this->columns[] = $col;
        return $col;
    }

    public function timestamp(string $name): Column
    {
        $col = new Column($name, 'timestamp');
        $this->columns[] = $col;
        return $col;
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

    /**
     * Add a foreign key definition.
     */
    public function foreign(string $column): ForeignKey
    {
        $column = trim($column);
        $fk = new ForeignKey($column !== '' ? $column : '__invalid__');
        $this->foreignKeys[] = $fk;
        return $fk;
    }

    /**
     * @return list<ForeignKey>
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function ensureHasColumnsForCreate(): void
    {
        if ($this->columns === []) {
            throw new RuntimeException("Table '{$this->table}' has no columns");
        }
    }
}
