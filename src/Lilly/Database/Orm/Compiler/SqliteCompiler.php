<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Compiler;

use Lilly\Database\Orm\Query\DeleteQuery;
use Lilly\Database\Orm\Query\InsertQuery;
use Lilly\Database\Orm\Query\SelectQuery;
use Lilly\Database\Orm\Query\UpdateQuery;

final class SqliteCompiler extends AbstractCompiler
{
    public function compileSelect(SelectQuery $q): CompiledSql
    {
        $bindings = [];
        $sql = 'SELECT ' . implode(', ', $q->columns) . ' FROM ' . $q->table;

        $sql .= $this->compileWheres($q->wheres, $bindings, 'w');

        if ($q->orderBys !== []) {
            $order = array_map(
                fn ($o) => $o['column'] . ' ' . $o['direction'],
                $q->orderBys
            );
            $sql .= ' ORDER BY ' . implode(', ', $order);
        }

        if ($q->limit !== null) {
            $sql .= ' LIMIT ' . $q->limit;
        }

        return new CompiledSql($sql, $bindings);
    }

    public function compileInsert(InsertQuery $q): CompiledSql
    {
        $bindings = [];
        $cols = array_keys($q->values);

        $placeholders = [];
        foreach ($cols as $i => $col) {
            $p = 'v' . $i;
            $placeholders[] = ':' . $p;
            $bindings[$p] = $q->values[$col];
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $q->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );

        return new CompiledSql($sql, $bindings);
    }

    public function compileUpdate(UpdateQuery $q): CompiledSql
    {
        $bindings = [];
        $setParts = [];

        $i = 0;
        foreach ($q->set as $col => $val) {
            $p = 's' . $i;
            $setParts[] = "{$col} = :{$p}";
            $bindings[$p] = $val;
            $i++;
        }

        $sql = 'UPDATE ' . $q->table . ' SET ' . implode(', ', $setParts);
        $sql .= $this->compileWheres($q->wheres, $bindings, 'w');

        return new CompiledSql($sql, $bindings);
    }

    public function compileDelete(DeleteQuery $q): CompiledSql
    {
        $bindings = [];
        $sql = 'DELETE FROM ' . $q->table;
        $sql .= $this->compileWheres($q->wheres, $bindings, 'w');
        return new CompiledSql($sql, $bindings);
    }
}
