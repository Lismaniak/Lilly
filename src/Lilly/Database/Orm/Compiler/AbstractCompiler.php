<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Compiler;

use Lilly\Database\Orm\Query\Where;
use RuntimeException;

abstract class AbstractCompiler implements Compiler
{
    protected function compileWheres(array $wheres, array &$bindings, string $prefix = 'w'): string
    {
        if ($wheres === []) {
            return '';
        }

        $parts = [];
        $i = 0;

        foreach ($wheres as $w) {
            if (!$w instanceof Where) {
                throw new RuntimeException('Invalid where');
            }
            $param = $prefix . $i;
            $parts[] = "{$w->column} {$w->op} :{$param}";
            $bindings[$param] = $w->value;
            $i++;
        }

        return ' WHERE ' . implode(' AND ', $parts);
    }
}
