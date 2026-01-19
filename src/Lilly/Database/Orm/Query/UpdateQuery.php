<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Query;

final class UpdateQuery
{
    /** @var list<Where> */
    public array $wheres = [];

    /**
     * @param array<string, mixed> $set column => value
     */
    public function __construct(
        public readonly string $table,
        public readonly array $set
    ) {}
}
