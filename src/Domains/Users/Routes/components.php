<?php
declare(strict_types=1);

use Domains\Users\Components\AddUserForm\Block;
use Domains\Users\Components\AddUserForm\Props;
use Domains\Users\Controllers\UsersController;
use Lilly\Http\DomainRouter;
use Lilly\Http\Request;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $render = static function (Props $props): string {
        $block = new Block($props);
        return $block->renderHtml();
    };

    $actionPath = '/users/components/add-user-form';

    $router->get($actionPath, function () use ($render, $actionPath): Response {
        $html = $render(new Props(actionPath: $actionPath));
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    });

    $router->post($actionPath, function (Request $request) use ($render, $actionPath): Response {
        $controller = new UsersController();
        $result = $controller->create($request);

        $notice = sprintf('Created user #%d (%s).', $result->id, $result->name);
        $html = $render(new Props(actionPath: $actionPath, notice: $notice));
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    });
};
