<?php
declare(strict_types=1);

namespace Lilly\Http;

final readonly class DomainRouter
{
    public function __construct(
        private Router $router,
        private string $domainKey
    ) {}

    /**
     * @param list<string> $gates
     */
    public function get(string $path, \Closure $handler, array $gates = []): void
    {
        $this->router->get($path, $handler, domains: [$this->domainKey], gates: $gates);
    }

    /**
     * @param list<string> $gates
     */
    public function post(string $path, \Closure $handler, array $gates = []): void
    {
        $this->router->post($path, $handler, domains: [$this->domainKey], gates: $gates);
    }
}
