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
        $summaries = array_map(
            static fn (Users $user): UserSummary => new UserSummary(
                $user->id ?? 0,
                $user->name
            ),
            $items
        );

        return new ListUsersResult($summaries);
    }
}
