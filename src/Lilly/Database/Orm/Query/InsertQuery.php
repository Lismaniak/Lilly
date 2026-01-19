<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class InsertQuery
{
    /**
     * @param array<string, mixed> $values column => value
     */
    public function __construct(
        public readonly string $table,
        public readonly array $values
    ) {}
}
