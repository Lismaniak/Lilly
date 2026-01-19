<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class DeleteQueryBuilder
{
    public function __construct(
        private readonly DeleteQuery $q
    ) {}

    public function where(string $column, string $op, mixed $value): self
    {
        $this->q->wheres[] = new Where($column, $op, $value);
        return $this;
    }

    public function build(): DeleteQuery
    {
        return $this->q;
    }
}
