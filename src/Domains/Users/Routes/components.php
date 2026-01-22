<?php
declare(strict_types=1);

use Domains\Users\Components\AddUser\Actions\AddUser;
use Domains\Users\Components\AddUser\Actions\AddUserInput;
use Domains\Users\Components\AddUser\Props;
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

    $viewPath = __DIR__ . '/../Components/AddUser/View/view.php';
    $actionPath = '/users/components/add-user';

    $router->get($actionPath, function () use ($render, $viewPath, $actionPath): Response {
        $html = $render($viewPath, new Props(actionPath: $actionPath));
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    });

    $router->post($actionPath, function (Request $request) use ($render, $viewPath, $actionPath): Response {
        $orm = $request->attribute('orm');
        $input = new AddUserInput((string) $request->input('name', ''));
        $action = new AddUser();
        $result = $action->handle($orm, $input);

        $notice = sprintf('Created user #%d (%s).', $result->id, $result->name);
        $html = $render($viewPath, new Props(actionPath: $actionPath, notice: $notice));
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    });
};
