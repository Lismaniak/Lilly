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

    $router->get('/users/dummy', fn (Request $request): Response => Response::json(
        (new ListUsersService(new UsersQueryRepository($request->attribute('orm'))))
            ->handle(new ListUsersQuery(limit: 3))
            ->toArray()
    ));
};
