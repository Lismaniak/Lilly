<?php
declare(strict_types=1);

namespace Domains\Users\Components\AddUser\Actions;

use Domains\Users\Repositories\UsersCommandRepository;
use Domains\Users\Services\Commands\CreateUserData;
use Domains\Users\Services\Commands\CreateUserResult;
use Domains\Users\Services\Commands\CreateUserService;
use Lilly\Database\Orm\Orm;

final class AddUser
{
    public function handle(Orm $orm, AddUserInput $input): CreateUserResult
    {
        $repository = new UsersCommandRepository($orm);
        $service = new CreateUserService($repository);

        return $service->handle(new CreateUserData($input->name));
    }
}
