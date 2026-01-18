<?php
declare(strict_types=1);

namespace Lilly\Database\Schema;

final class ForeignKey
{
    public ?string $refTable = null;
    public ?string $refColumn = null;

    public ?string $onDelete = null; // cascade|restrict|set-null|no-action
    public ?string $onUpdate = null; // cascade|restrict|set-null|no-action

    public ?string $name = null;

    public function __construct(
        public readonly string $column,
    ) {}

    public function references(string $table, string $column = 'id'): self
    {
        $this->refTable = $table;
        $this->refColumn = $column;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
