<?php
declare(strict_types=1);

namespace Lilly\Database\Orm;

use Lilly\Database\Orm\Compiler\Compiler;
use Lilly\Database\Orm\Compiler\CompiledSql;
use Lilly\Database\Orm\Metadata\MetadataFactory;
use Lilly\Database\Orm\Query\DeleteQuery;
use Lilly\Database\Orm\Query\InsertQuery;
use Lilly\Database\Orm\Query\QueryBuilder;
use Lilly\Database\Orm\Query\SelectQuery;
use Lilly\Database\Orm\Query\UpdateQuery;
use PDO;

final class Orm
{
    public readonly QueryBuilder $qb;
    public readonly MetadataFactory $meta;

    public function __construct(
        public readonly PDO $pdo,
        public readonly Compiler $compiler
    ) {
        $this->qb = new QueryBuilder();
        $this->meta = new MetadataFactory();
    }

    public function compileSelect(SelectQuery $q): CompiledSql { return $this->compiler->compileSelect($q); }
    public function compileInsert(InsertQuery $q): CompiledSql { return $this->compiler->compileInsert($q); }
    public function compileUpdate(UpdateQuery $q): CompiledSql { return $this->compiler->compileUpdate($q); }
    public function compileDelete(DeleteQuery $q): CompiledSql { return $this->compiler->compileDelete($q); }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(SelectQuery $q): array
    {
        $c = $this->compileSelect($q);
        $stmt = $this->pdo->prepare($c->sql);
        $stmt->execute($c->bindings);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(SelectQuery $q): ?array
    {
        $q->limit = 1;
        $all = $this->fetchAll($q);
        return $all[0] ?? null;
    }

    public function execInsert(InsertQuery $q): int
    {
        $c = $this->compileInsert($q);
        $stmt = $this->pdo->prepare($c->sql);
        $stmt->execute($c->bindings);
        return (int) $this->pdo->lastInsertId();
    }

    public function execUpdate(UpdateQuery $q): int
    {
        $c = $this->compileUpdate($q);
        $stmt = $this->pdo->prepare($c->sql);
        $stmt->execute($c->bindings);
        return $stmt->rowCount();
    }

    public function execDelete(DeleteQuery $q): int
    {
        $c = $this->compileDelete($q);
        $stmt = $this->pdo->prepare($c->sql);
        $stmt->execute($c->bindings);
        return $stmt->rowCount();
    }
}
