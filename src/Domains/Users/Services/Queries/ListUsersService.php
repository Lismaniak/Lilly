<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Entities\Users;
use Domains\Users\Repositories\UsersQueryRepository;

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
        $items = $this->users->listDummy($limit);

        return array_map(
            static fn (Users $user): array => [
                'id' => $user->id ?? 0,
                'name' => $user->name,
            ],
            $items
        );
    }
}
