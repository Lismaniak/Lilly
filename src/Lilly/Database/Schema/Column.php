<?php
declare(strict_types=1);

namespace Lilly\Database\Schema;

use RuntimeException;

final class Column
{
    /**
     * Back-reference to the Blueprint that created this column.
     * This is set by Blueprint when the Column is instantiated.
     */
    private ?Blueprint $blueprint = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public bool $nullable = false,
        public bool $unique = false,
        public bool $primary = false,
        public bool $autoIncrement = false,
        public mixed $default = null,
    ) {}

    /**
     * Blueprint will call this right after creating the column.
     * Not part of the public fluent API.
     */
    public function bindBlueprint(Blueprint $blueprint): void
    {
        $this->blueprint = $blueprint;
    }

    /**
     * Declare legacy names for this column so sync can emit a safe rename.
     *
     * Usage:
     *  $t->string('name')->was('full_name');
     *  $t->string('name')->was(['full_name', 'fullname']);
     *
     * Rules:
     * - Only valid when Column was created by Blueprint.
     * - Empty names are ignored.
     * - Target name itself is ignored.
     *
     * @param string|list<string> $from
     */
    public function was(string|array $from): self
    {
        if ($this->blueprint === null) {
            throw new RuntimeException("was() can only be used on columns created by Blueprint (column '{$this->name}')");
        }

        $list = is_array($from) ? $from : [$from];
        $this->blueprint->registerWas($this->name, $list);

        return $this;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }
}
