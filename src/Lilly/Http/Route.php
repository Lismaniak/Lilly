<?php
declare(strict_types=1);

namespace Lilly\Http;

final readonly class Route
{
    /**
     * @param list<string> $domains Domain keys (example: ["users"])
     * @param list<string> $gates Gate names (example: ["users.view"])
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $domains,
        public array $gates,
        public \Closure $handler,
    ) {}
}
