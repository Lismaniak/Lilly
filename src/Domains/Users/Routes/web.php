<?php
declare(strict_types=1);

use Domains\Users\Repositories\UsersQueryRepository;
use Domains\Users\Services\Queries\ListUsersService;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $router->get('/users/health', fn (): Response => Response::json(['ok' => true]));

    $router->get('/users/dummy', function (Request $request): Response {
        $orm = $request->attribute('orm');
        $repository = new UsersQueryRepository($orm);
        $service = new ListUsersService($repository);

        return Response::json($service->list());
    });
};
