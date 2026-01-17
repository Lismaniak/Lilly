<?php

declare(strict_types=1);

namespace Lilly\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string|int|float|bool|null> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public array $query = [],
        public array $body = [],
        public array $attributes = [],
    ) {}

    public function header(string $name, ?string $default = null): ?string
    {
        $needle = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $needle) {
                return $value;
            }
        }

        return $default;
    }

    public function query(string $key, string|int|float|bool|null $default = null): string|int|float|bool|null
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self(
            method: $this->method,
            path: $this->path,
            headers: $this->headers,
            query: $this->query,
            body: $this->body,
            attributes: $attributes,
        );
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
