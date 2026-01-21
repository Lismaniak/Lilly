<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Entities\Users;
use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\DtoGuard;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;

final class ListUsersService
{
    public function __construct(
        private readonly UsersQueryRepository $users,
    ) {
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    public function list(int $limit = 3): array
    {
        $query = new ListUsersQuery($limit);
        DtoGuard::assertQueryDto($query);

        $items = $this->users->listDummy($query->limit);

        $result = new ListUsersResult(array_map(
            static fn (Users $user): array => [
                'id' => $user->id ?? 0,
                'name' => $user->name,
            ],
            $items
        ));

        DtoGuard::assertResultDto($result);

        return $result->items;
    }
}

readonly class ListUsersQuery implements QueryDto
{
    public function __construct(public int $limit = 3)
    {
    }
}

readonly class ListUsersResult implements ResultDto
{
    /**
     * @param list<array{id:int, name:string}> $items
     */
    public function __construct(public array $items)
    {
    }
}
