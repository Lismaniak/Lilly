<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Metadata;

final class EntityMetadata
{
    /**
     * @param array<string, string> $propertyToColumn
     * @param array<string, string> $columnToProperty
     */
    public function __construct(
        public readonly string $class,
        public readonly string $table,
        public readonly string $primaryProperty,
        public readonly string $primaryColumn,
        public readonly bool $primaryAutoIncrement,
        public readonly array $propertyToColumn,
        public readonly array $columnToProperty,
    ) {}
}
