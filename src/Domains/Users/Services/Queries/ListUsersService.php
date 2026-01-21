<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Entities\Users;
use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;
use InvalidArgumentException;

final class ListUsersService extends QueryService
{
    public function __construct(
        private readonly UsersQueryRepository $users,
    ) {
    }

    /**
     * @return list<array{id:int, name:string, created_at:string, updated_at:string}>
     */
    public function list(
        int $limit = ListUsersQuery::DEFAULT_LIMIT,
        int $offset = ListUsersQuery::DEFAULT_OFFSET
    ): array
    {
        $result = $this->handle(new ListUsersQuery($limit, $offset));
        return $result->items;
    }

    protected function execute(QueryDto $query): ResultDto
    {
        if (!$query instanceof ListUsersQuery) {
            throw new InvalidArgumentException('Expected ' . ListUsersQuery::class);
        }

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
}

readonly class ListUsersQuery implements QueryDto
{
    public const DEFAULT_LIMIT = 3;
    public const DEFAULT_OFFSET = 0;

    public function __construct(
        public int $limit = self::DEFAULT_LIMIT,
        public int $offset = self::DEFAULT_OFFSET
    ) {
    }
}

readonly class ListUsersResult implements ResultDto
{
    /**
     * @param list<array{id:int, name:string, created_at:string, updated_at:string}> $items
     */
    public function __construct(public array $items)
    {
    }
}
