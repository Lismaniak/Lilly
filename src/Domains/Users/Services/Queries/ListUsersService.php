<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Entities\Users;
use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;
use RuntimeException;

final class ListUsersService extends QueryService
{
    public function __construct(
        private readonly UsersQueryRepository $users,
    ) {
    }

    protected function execute(QueryDto $query): ResultDto
    {
        if (!$query instanceof ListUsersQuery) {
            throw new RuntimeException('ListUsersService expects ListUsersQuery');
        }

        $items = $this->users->listDummy($query->limit);

        return new ListUsersResult(
            array_map(
                static fn (Users $user): array => [
                    'id' => $user->id ?? 0,
                    'name' => $user->name,
                ],
                $items
            )
        );
    }
}

final readonly class ListUsersQuery implements QueryDto
{
    public function __construct(
        public int $limit = 3,
    ) {
        $this->limit = max(1, min($this->limit, 50));
    }
}

final readonly class ListUsersResult implements ResultDto
{
    /**
     * @param list<array{id:int, name:string}> $users
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
        return ['users' => $this->users];
    }
}
