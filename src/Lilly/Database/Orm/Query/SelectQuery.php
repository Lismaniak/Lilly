<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class SelectQuery
{
    /** @var list<string> */
    public array $columns = ['*'];

    /** @var list<Where> */
    public array $wheres = [];

    /** @var list<array{column: string, direction: 'ASC'|'DESC'}> */
    public array $orderBys = [];

    public ?int $limit = null;

    public function __construct(
        public readonly string $table
    ) {}
}
