<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\QueryDto;

final readonly class ListUsersQuery implements QueryDto
{
    public function __construct(
        public int $limit = 3,
    ) {
    }
}
