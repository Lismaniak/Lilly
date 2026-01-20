<?php
declare(strict_types=1);

use Domains\Users\Repositories\UsersQueryRepository;
use Domains\Users\Services\Queries\ListUsersQuery;
use Domains\Users\Services\Queries\ListUsersService;
use Lilly\Database\Orm\Orm;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $router->get(
        '/users/health',
        fn () => Response::json(['ok' => true])
    );

    $router->get(
        '/users/dummy',
        function (Request $request): Response {
            $orm = $request->attribute('orm');
            if (!$orm instanceof Orm) {
                return Response::text('ORM missing from request.', 500);
            }

            $repo = new UsersQueryRepository($orm);
            $service = new ListUsersService($repo);
            $result = $service->handle(new ListUsersQuery(limit: 3));

            return Response::json($result->toArray());
        }
    );
};
