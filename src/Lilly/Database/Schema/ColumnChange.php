<?php
declare(strict_types=1);

namespace Lilly\Database\Schema;

final class ColumnChange
{
    public function __construct(
        public readonly string $name,
        public ?string $type = null,         // e.g. 'string:120', 'text', 'int', 'bool', 'timestamp'
        public ?bool $nullable = null,       // null means "keep existing"
        public mixed $default = '__KEEP__',  // '__KEEP__' means "keep existing", null means "set default null"
        public ?bool $unique = null,         // null means "keep existing"
    ) {}

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }
}
