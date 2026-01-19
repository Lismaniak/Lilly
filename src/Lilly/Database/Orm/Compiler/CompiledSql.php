<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Compiler;

final class CompiledSql
{
    /**
     * @param array<string, mixed> $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings
    ) {}
}
