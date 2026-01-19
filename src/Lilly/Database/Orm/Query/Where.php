<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class Where
{
    public function __construct(
        public readonly string $column,
        public readonly string $op,
        public readonly mixed $value
    ) {}
}
