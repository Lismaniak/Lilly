<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

final class SchemaSyncResult
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public readonly bool $ok,
        public readonly array $lines
    ) {}
}
