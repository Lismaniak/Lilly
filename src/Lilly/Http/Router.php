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

    public function match(Request $request): MatchedRoute
    {
        // Pass 1: static routes only
        foreach ($this->routes as $route) {
            if ($route->method !== $request->method) {
                continue;
            }

            if ($route->path === $request->path) {
                return new MatchedRoute($route, []);
            }
        }

        // Pass 2: dynamic routes
        foreach ($this->routes as $route) {
            if ($route->method !== $request->method) {
                continue;
            }

            $params = $this->matchPath($route->path, $request->path);
            if ($params === null) {
                continue;
            }

            return new MatchedRoute($route, $params);
        }

        throw new \RuntimeException("No route for {$request->method} {$request->path}");
    }

    /**
     * Supports:
     * - exact: /health
     * - params: /users/{id}
     *
     * @return array<string, string>|null
     */
    private function matchPath(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        if (!str_contains($pattern, '{')) {
            return null;
        }

        $paramNames = [];

        $regex = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                return '([^\/]+)';
            },
            preg_quote($pattern, '#')
        );

        if (!is_string($regex)) {
            return null;
        }

        $regex = '#^' . $regex . '$#';

        $matches = [];
        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);

        $params = [];
        foreach ($paramNames as $i => $name) {
            $params[$name] = isset($matches[$i]) ? (string) $matches[$i] : '';
        }

        return $params;
    }
}
