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
    public string $name;
    public int $limit;

    public function __construct(string $name, int $limit)
    {
        $data = ArrayValidator::map(
            ['name' => $name, 'limit' => $limit],
            [
                'name' => ['required', 'string', 'max:255', 'non_empty'],
                'limit' => ['required', 'int'],
            ]
        );

        $this->name = $data['name'];
        $this->limit = $data['limit'];
    }
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

        $items = $this->users->listDummy();
        $needle = strtolower($query->name);
        $items = array_values(array_filter(
            $items,
            static fn (Users $user): bool => strtolower($user->name) === $needle
        ));
        $limit = max(0, $query->limit);
        $items = array_slice($items, 0, $limit);

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
