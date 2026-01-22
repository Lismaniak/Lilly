<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUser\Actions;

use Domains\Users\Repositories\UsersCommandRepository;
use Domains\Users\Services\Commands\CreateUserData;
use Domains\Users\Services\Commands\CreateUserService;
use Lilly\Database\Orm\Orm;
use Lilly\Dto\ResultDto;
use Lilly\Validation\ArrayValidator;

final readonly class AddUserInput
{
    public string $name;

    public function __construct(string $name)
    {
        $data = ArrayValidator::map(
            ['name' => $name],
            ['name' => ['required', 'string', 'max:255', 'non_empty']]
        );

        $this->name = $data['name'];
    }
}

final class AddUser
{
    public function handle(Orm $orm, AddUserInput $input): ResultDto
    {
        $repository = new UsersCommandRepository($orm);
        $service = new CreateUserService($repository);
        $data = new CreateUserData($input->name);

        return $service->handle($data->name);
    }
}
