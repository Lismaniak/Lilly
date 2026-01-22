<?php
declare(strict_types=1);

use Domains\Users\Controllers\UsersController;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $controller = new UsersController();

    $router->get('/users/health', fn (): Response => $controller->health());
    $router->get('/users/create/{name}', fn (Request $request): Response => $controller->createResponse($request));
    $router->get('/users/{name}/{limit}', fn (Request $request): Response => $controller->list($request));
};
