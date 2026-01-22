<?php
declare(strict_types=1);

use Domains\Users\Components\AddUserForm\Props;
use Domains\Users\Controllers\UsersController;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $render = static function (string $viewPath, Props $props): string {
        $props = $props;
        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    };

    $viewPath = __DIR__ . '/../Components/AddUserForm/View/view.php';
    $actionPath = '/users/components/add-user-form';

    $router->get($actionPath, function () use ($render, $viewPath, $actionPath): Response {
        $html = $render($viewPath, new Props(actionPath: $actionPath));
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    });

    $router->post($actionPath, function (Request $request) use ($render, $viewPath, $actionPath): Response {
        $controller = new UsersController();
        $result = $controller->create($request);

        $notice = sprintf('Created user #%d (%s).', $result->id, $result->name);
        $html = $render($viewPath, new Props(actionPath: $actionPath, notice: $notice));
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    });
};
