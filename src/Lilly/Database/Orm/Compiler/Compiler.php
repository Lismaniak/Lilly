<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Compiler;

use Lilly\Database\Orm\Query\DeleteQuery;
use Lilly\Database\Orm\Query\InsertQuery;
use Lilly\Database\Orm\Query\SelectQuery;
use Lilly\Database\Orm\Query\UpdateQuery;

interface Compiler
{
    public function compileSelect(SelectQuery $q): CompiledSql;
    public function compileInsert(InsertQuery $q): CompiledSql;
    public function compileUpdate(UpdateQuery $q): CompiledSql;
    public function compileDelete(DeleteQuery $q): CompiledSql;
}
