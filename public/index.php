<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Lilly\Http\Request;
use Lilly\Http\Response;
use Lilly\Http\Kernel;

$kernel = new Kernel(projectRoot: realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));

$request = Request::fromGlobals();

$response = $kernel->handle($request);

http_response_code($response->status);

foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}

echo $response->body;
