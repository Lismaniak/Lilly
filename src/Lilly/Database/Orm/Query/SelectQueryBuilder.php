<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

use RuntimeException;

final class SelectQueryBuilder
{
    public function __construct(
        private readonly SelectQuery $q
    ) {}

    /**
     * @param list<string> $columns
     */
    public function columns(array $columns): self
    {
        if ($columns === []) {
            throw new RuntimeException('columns cannot be empty');
        }
        $this->q->columns = $columns;
        return $this;
    }

    public function where(string $column, string $op, mixed $value): self
    {
        $this->q->wheres[] = new Where($column, $op, $value);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction);
        if ($dir !== 'ASC' && $dir !== 'DESC') {
            throw new RuntimeException("Invalid order direction: {$direction}");
        }
        $this->q->orderBys[] = ['column' => $column, 'direction' => $dir];
        return $this;
    }

    public function limit(int $n): self
    {
        if ($n < 1) {
            throw new RuntimeException('limit must be >= 1');
        }
        $this->q->limit = $n;
        return $this;
    }

    public function build(): SelectQuery
    {
        return $this->q;
    }
}
