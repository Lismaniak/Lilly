<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Entities\Users;
use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

readonly class ListUsersQuery implements QueryDto
{
    public function __construct(
        public int $limit = 3,
        public int $offset = 0
    ){}
}

readonly class ListUsersResult implements ResultDto
{
    /**
     * @param list<array{id:int, name:string, created_at:string, updated_at:string}> $items
     */
    public function __construct(
        public array $items = []
    ) {}
}

final class ListUsersService extends QueryService
{
    public function __construct(
        private readonly UsersQueryRepository $users,
    ) {}

    protected function execute(QueryDto $query): ResultDto
    {
        /** @var ListUsersQuery $query */

        $items = $this->users->listDummy($query->limit + $query->offset);
        $items = array_slice($items, $query->offset, $query->limit);

        return new ListUsersResult(array_map(
            static fn (Users $user): array => [
                'id' => $user->id ?? 0,
                'name' => $user->name,
                'created_at' => $user->createdAt ?? '',
                'updated_at' => $user->updatedAt ?? '',
            ],
            $items
        ));
    }

    protected function expectedQueryClass(): ?string
    {
        return ListUsersQuery::class;
    }

    protected function expectedResultClass(): ?string
    {
        return ListUsersResult::class;
    }
}
