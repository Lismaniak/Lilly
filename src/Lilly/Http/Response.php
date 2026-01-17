<?php
declare(strict_types=1);

namespace Lilly\Http;

final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/plain; charset=utf-8'],
        public string $body = ''
    ) {}

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
        );
    }
}
