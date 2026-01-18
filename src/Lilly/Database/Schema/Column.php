<?php
declare(strict_types=1);

namespace Lilly\Database\Schema;

final class Column
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public bool $nullable = false,
        public bool $unique = false,
        public bool $primary = false,
        public bool $autoIncrement = false,
        public mixed $default = null,
    ) {}

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
