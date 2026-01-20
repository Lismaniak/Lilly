<?php
declare(strict_types=1);

use Lilly\Http\DomainRouter;
use Lilly\Http\Response;

return function (DomainRouter $router): void {
    $router->get(
        '/users/health',
        fn () => Response::json(['ok' => true])
    );
};
