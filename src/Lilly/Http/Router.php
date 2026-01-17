<?php
declare(strict_types=1);

namespace Lilly\Http;

use RuntimeException;

final class Router
{
    /**
     * @var list<Route>
     */
    private array $routes = [];

    /**
     * @param list<string> $domains
     * @param list<string> $gates
     */
    public function get(string $path, \Closure $handler, array $domains = [], array $gates = []): void
    {
        $this->routes[] = new Route('GET', $path, $domains, $gates, $handler);
    }

    /**
     * @param list<string> $domains
     * @param list<string> $gates
     */
    public function post(string $path, \Closure $handler, array $domains = [], array $gates = []): void
    {
        $this->routes[] = new Route('POST', $path, $domains, $gates, $handler);
    }

    public function match(Request $request): Route
    {
        foreach ($this->routes as $route) {
            if ($route->method !== $request->method) {
                continue;
            }

            if ($route->path !== $request->path) {
                continue;
            }

            return $route;
        }

        throw new RuntimeException("No route for {$request->method} {$request->path}");
    }
}
