<?php
declare(strict_types=1);

use Domains\Users\Repositories\UsersQueryRepository;
use Domains\Users\Services\Queries\BrokenNonReadonlyQueryService;
use Domains\Users\Services\Queries\BrokenNonReadonlyResultService;
use Domains\Users\Services\Queries\BrokenWrongSuffixQueryService;
use Domains\Users\Services\Queries\BrokenWrongSuffixResultService;
use Domains\Users\Services\Queries\ListUsersQuery;
use Domains\Users\Services\Queries\ListUsersService;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $router->get('/users/health', fn (): Response => Response::json(['ok' => true]));

    $router->get('/users/{name}', function (Request $request): Response {
        $orm = $request->attribute('orm');
        $name = (string) $request->attribute('name', '');
        $repository = new UsersQueryRepository($orm);
        $service = new ListUsersService($repository);
        $result = $service->handle(new ListUsersQuery($name));

        return Response::json($result->items);
    });

    $router->get('/users/broken/non-readonly-query', function (): Response {
        $service = new BrokenNonReadonlyQueryService();
        $service->run();

        return Response::json(['ok' => true]);
    });

    $router->get('/users/broken/wrong-suffix-query', function (): Response {
        $service = new BrokenWrongSuffixQueryService();
        $service->run();

        return Response::json(['ok' => true]);
    });

    $router->get('/users/broken/non-readonly-result', function (): Response {
        $service = new BrokenNonReadonlyResultService();
        $service->run();

        return Response::json(['ok' => true]);
    });

    $router->get('/users/broken/wrong-suffix-result', function (): Response {
        $service = new BrokenWrongSuffixResultService();
        $service->run();

        return Response::json(['ok' => true]);
    });
};
