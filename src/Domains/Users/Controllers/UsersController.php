<?php
declare(strict_types=1);

namespace Domains\Users\Controllers;

use Domains\Users\Components\AddUserForm\AddUserFormInput;
use Domains\Users\Services\Commands\CreateUserData;
use Domains\Users\Services\Commands\CreateUserResult;
use Domains\Users\Services\Commands\CreateUserService;
use Domains\Users\Services\Queries\ListUsersQuery;
use Domains\Users\Services\Queries\ListUsersService;
use Domains\Users\Repositories\UsersCommandRepository;
use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\ResultDto;
use Lilly\Http\Request;
use Lilly\Http\Response;

final class UsersController
{
    public function health(): Response
    {
        return Response::json(['ok' => true]);
    }

    public function create(Request $request): ResultDto
    {
        $orm = $request->attribute('orm');
        $input = new AddUserFormInput((string) $request->input('name', $request->attribute('name', '')));
        $repository = new UsersCommandRepository($orm);
        $service = new CreateUserService($repository);

        return $service->handle(new CreateUserData($input->name));
    }

    public function createResponse(Request $request): Response
    {
        $result = $this->create($request);

        return Response::json([
            'id' => $result->id,
            'name' => $result->name,
            'created_at' => $result->createdAt,
            'updated_at' => $result->updatedAt,
        ]);
    }

    public function list(Request $request): Response
    {
        $orm = $request->attribute('orm');
        $name = (string) $request->attribute('name', '');
        $limit = (int) $request->attribute('limit', 0);
        $repository = new UsersQueryRepository($orm);
        $service = new ListUsersService($repository);
        $result = $service->handle(new ListUsersQuery($name, $limit));

        return Response::json($result->items);
    }
}
