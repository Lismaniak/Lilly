<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public readonly string $name,
        public readonly bool $primary = false,
        public readonly bool $autoIncrement = false,
        public readonly bool $nullable = false,
    ) {}
}
