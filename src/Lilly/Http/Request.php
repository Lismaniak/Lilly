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

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $query = [];
        foreach ($_GET as $k => $v) {
            if (is_string($k)) {
                $query[$k] = is_scalar($v) ? $v : null;
            }
        }

        $body = [];
        $contentType = $headers['content-type'] ?? '';
        $raw = file_get_contents('php://input');

        if (is_string($raw) && $raw !== '') {
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            } else {
                foreach ($_POST as $k => $v) {
                    if (is_string($k)) {
                        $body[$k] = $v;
                    }
                }
            }
        } else {
            foreach ($_POST as $k => $v) {
                if (is_string($k)) {
                    $body[$k] = $v;
                }
            }
        }

        return new self(
            method: strtoupper($method),
            path: $path,
            headers: $headers,
            query: $query,
            body: $body,
            attributes: [],
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $needle = strtolower($name);
        return $this->headers[$needle] ?? $default;
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
