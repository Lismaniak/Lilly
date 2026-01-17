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
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $headers = self::collectHeaders($_SERVER);

        $query = self::collectQuery($_GET);

        $contentType = $headers['content-type'] ?? '';
        $body = self::collectBody($method, $contentType, $_POST);

        return new self(
            method: $method,
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

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function collectHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
                continue;
            }

            // Non-HTTP_ headers that PHP exposes separately
            if ($key === 'CONTENT_TYPE') {
                $headers['content-type'] = $value;
                continue;
            }

            if ($key === 'CONTENT_LENGTH') {
                $headers['content-length'] = $value;
                continue;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $get
     * @return array<string, string|int|float|bool|null>
     */
    private static function collectQuery(array $get): array
    {
        $query = [];

        foreach ($get as $k => $v) {
            if (!is_string($k)) {
                continue;
            }

            $query[$k] = is_scalar($v) ? $v : null;
        }

        return $query;
    }

    /**
     * Rules:
     * - application/json: parse php://input for any method
     * - application/x-www-form-urlencoded:
     *     - if PHP filled $_POST (usually POST), use it
     *     - else parse php://input (PUT/PATCH/DELETE)
     * - multipart/form-data: rely on $_POST (PHP handles it)
     * - anything else: empty array
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function collectBody(string $method, string $contentType, array $post): array
    {
        $contentType = strtolower(trim(explode(';', $contentType, 2)[0] ?? ''));

        if ($contentType === '') {
            return $method === 'POST' ? self::sanitizeBodyArray($post) : [];
        }

        if ($contentType === 'application/json') {
            $raw = self::readRawBody();
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        if ($contentType === 'application/x-www-form-urlencoded') {
            if ($post !== []) {
                return self::sanitizeBodyArray($post);
            }

            $raw = self::readRawBody();
            if ($raw === '') {
                return [];
            }

            $data = [];
            parse_str($raw, $data);

            return is_array($data) ? $data : [];
        }

        if ($contentType === 'multipart/form-data') {
            return self::sanitizeBodyArray($post);
        }

        return $method === 'POST' ? self::sanitizeBodyArray($post) : [];
    }

    private static function readRawBody(): string
    {
        $raw = file_get_contents('php://input');
        return is_string($raw) ? $raw : '';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitizeBodyArray(array $data): array
    {
        $out = [];

        foreach ($data as $k => $v) {
            if (!is_string($k)) {
                continue;
            }

            $out[$k] = $v;
        }

        return $out;
    }
}
