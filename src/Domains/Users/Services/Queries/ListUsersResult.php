<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\ResultDto;

final readonly class ListUsersResult implements ResultDto
{
    /**
     * @param list<UserSummary> $users
     */
    public function __construct(
        public array $users,
    ) {
    }

    /**
     * @return array{users:list<array{id:int, name:string}>}
     */
    public function toArray(): array
    {
        return [
            'users' => array_map(
                static fn (UserSummary $user): array => $user->toArray(),
                $this->users
            ),
        ];
    }
}
