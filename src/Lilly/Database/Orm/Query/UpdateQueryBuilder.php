<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class UpdateQueryBuilder
{
    public function __construct(
        private readonly UpdateQuery $q
    ) {}

    public function where(string $column, string $op, mixed $value): self
    {
        $this->q->wheres[] = new Where($column, $op, $value);
        return $this;
    }

    public function build(): UpdateQuery
    {
        return $this->q;
    }
}
