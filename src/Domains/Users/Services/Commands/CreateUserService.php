<?php
declare(strict_types=1);

namespace Domains\Users\Services\Commands;

use Domains\Users\Repositories\UsersCommandRepository;
use Lilly\Dto\CommandDataDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\CommandService;
use Lilly\Validation\ArrayValidator;

readonly class CreateUserData implements CommandDataDto
{
    public string $name;

    public function __construct(string $name)
    {
        $data = ArrayValidator::map(
            ['name' => $name],
            [
                'name' => ['required', 'string', 'max:255', 'non_empty'],
            ]
        );

        $this->name = $data['name'];
    }
}

readonly class CreateUserResult implements ResultDto
{
    public int $id;
    public string $name;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(int $id, string $name, string $createdAt, string $updatedAt)
    {
        $data = ArrayValidator::map(
            [
                'id' => $id,
                'name' => $name,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ],
            [
                'id' => ['required', 'int'],
                'name' => ['required', 'string', 'max:255'],
                'created_at' => ['required', 'string'],
                'updated_at' => ['required', 'string'],
            ]
        );

        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }
}

final class CreateUserService extends CommandService
{
    public function __construct(
        private readonly UsersCommandRepository $users,
    ) {}

    protected function execute(CommandDataDto $data): ResultDto
    {
        /** @var CreateUserData $data */

        $user = $this->users->createWithName($data->name);

        return new CreateUserResult(
            $user->id ?? 0,
            $user->name,
            $user->createdAt ?? '',
            $user->updatedAt ?? ''
        );
    }

    protected function expectedDataClass(): ?string
    {
        return CreateUserData::class;
    }

    protected function expectedResultClass(): ?string
    {
        return CreateUserResult::class;
    }
}
