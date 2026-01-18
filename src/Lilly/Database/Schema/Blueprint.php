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

    public function id(string $name = 'id'): Column
    {
        $col = new Column($name, 'id', nullable: false, unique: false, primary: true, autoIncrement: true);
        $this->columns[] = $col;
        return $col;
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

    public function integer(string $name): Column
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

    public function ensureHasColumnsForCreate(): void
    {
        if ($this->columns === []) {
            throw new RuntimeException("Table '{$this->table}' has no columns");
        }
    }
}
