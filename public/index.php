<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Lilly\Http\Request;
use Lilly\Http\Kernel;
use Lilly\Config\EnvLoader;
use Lilly\Config\Config;

EnvLoader::load(projectRoot: realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));

$config = Config::fromEnv();

dd($config);

$projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

$kernel = new Kernel(
    projectRoot: $projectRoot,
    config: $config,
);


$request = Request::fromGlobals();

$response = $kernel->handle($request);

http_response_code($response->status);

foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}

echo $response->body;
