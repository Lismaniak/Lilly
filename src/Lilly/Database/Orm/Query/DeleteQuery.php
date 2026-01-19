<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class DeleteQuery
{
    /** @var list<Where> */
    public array $wheres = [];

    public function __construct(
        public readonly string $table
    ) {}
}
