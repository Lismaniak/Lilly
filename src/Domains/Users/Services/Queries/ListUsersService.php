<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Entities\Users;
use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;
use Lilly\Validation\ArrayValidator;

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
     * @param list<Users> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = ArrayValidator::mapListWithSchema($items, [
            'id' => [
                'value' => 'id',
                'rules' => ['required', 'int'],
            ],
            'name' => [
                'value' => 'name',
                'rules' => ['required', 'string', 'max:255'],
            ],
            'created_at' => [
                'value' => 'createdAt',
                'rules' => ['required', 'string'],
            ],
            'updated_at' => [
                'value' => 'updatedAt',
                'rules' => ['required', 'string'],
            ],
        ]);
    }

    /**
     * @var list<array{id:int, name:string, created_at:string, updated_at:string}>
     */
    public array $items;

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

        return new ListUsersResult($items);
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
