<?php
declare(strict_types=1);

namespace Lilly\Http;

final readonly class AppRouter
{
    public function __construct(
        private Router $router
    ) {}

    /**
     * @param list<string> $gates
     */
    public function get(string $path, \Closure $handler, array $gates = []): void
    {
        $this->router->get($path, $handler, domains: ['app'], gates: $gates);
    }

    /**
     * @param list<string> $gates
     */
    public function post(string $path, \Closure $handler, array $gates = []): void
    {
        $this->router->post($path, $handler, domains: ['app'], gates: $gates);
    }
}
