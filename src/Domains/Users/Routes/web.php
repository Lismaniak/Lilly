<?php
declare(strict_types=1);

use Domains\Users\Repositories\UsersQueryRepository;
use Domains\Users\Services\Queries\ListUsersQuery;
use Domains\Users\Services\Queries\ListUsersService;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $router->get('/users/health', fn (): Response => Response::json(['ok' => true]));

    $router->get('/users/{name}/{limit}', function (Request $request): Response {
        $orm = $request->attribute('orm');
        $name = (string) $request->attribute('name', '');
        $limit = (int) $request->attribute('limit', 0);
        $repository = new UsersQueryRepository($orm);
        $service = new ListUsersService($repository);
        $result = $service->handle(new ListUsersQuery($name, $limit));

        return Response::json($result->items);
    });
};
