<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Compiler;

use Lilly\Database\Orm\Query\DeleteQuery;
use Lilly\Database\Orm\Query\InsertQuery;
use Lilly\Database\Orm\Query\SelectQuery;
use Lilly\Database\Orm\Query\UpdateQuery;

final class MysqlCompiler extends AbstractCompiler
{
    public function compileSelect(SelectQuery $q): CompiledSql
    {
        $compiled = (new SqliteCompiler())->compileSelect($q);

        if (str_contains($compiled->sql, 'DELETE ') || str_contains($compiled->sql, 'UPDATE ')) {
            return $compiled;
        }

        return $compiled;
    }

    public function compileInsert(InsertQuery $q): CompiledSql
    {
        return (new SqliteCompiler())->compileInsert($q);
    }

    public function compileUpdate(UpdateQuery $q): CompiledSql
    {
        return (new SqliteCompiler())->compileUpdate($q);
    }

    public function compileDelete(DeleteQuery $q): CompiledSql
    {
        return (new SqliteCompiler())->compileDelete($q);
    }
}
