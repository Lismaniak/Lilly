<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

use RuntimeException;

final class QueryBuilder
{
    public function select(string $table): SelectQueryBuilder
    {
        return new SelectQueryBuilder(new SelectQuery($table));
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insert(string $table, array $values): InsertQuery
    {
        return new InsertQuery($table, $values);
    }

    /**
     * @param array<string, mixed> $set
     */
    public function update(string $table, array $set): UpdateQueryBuilder
    {
        if ($set === []) {
            throw new RuntimeException('Update requires non empty set');
        }
        return new UpdateQueryBuilder(new UpdateQuery($table, $set));
    }

    public function delete(string $table): DeleteQueryBuilder
    {
        return new DeleteQueryBuilder(new DeleteQuery($table));
    }
}
