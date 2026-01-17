<?php
declare(strict_types=1);

namespace Lilly\Http;

final readonly class CrossDomainRouter
{
    /**
     * @param list<string> $domainKeys
     */
    public function __construct(
        private Router $router,
        private array $domainKeys
    ) {}

    /**
     * @param list<string> $gates
     */
    public function get(string $path, \Closure $handler, array $gates = []): void
    {
        $this->router->get($path, $handler, domains: $this->domainKeys, gates: $gates);
    }

    /**
     * @param list<string> $gates
     */
    public function post(string $path, \Closure $handler, array $gates = []): void
    {
        $this->router->post($path, $handler, domains: $this->domainKeys, gates: $gates);
    }

    /**
     * Expose domains for debugging/introspection if needed.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->domainKeys;
    }
}
