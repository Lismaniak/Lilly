<?php
declare(strict_types=1);

namespace Lilly\Config;

final readonly class Config
{
    public function __construct(
        public string $appEnv,
        public bool $appDebug,

        public string $dbConnection,
        public string $dbDatabase,
        public ?string $dbHost,
        public ?int $dbPort,
        public ?string $dbUsername,
        public ?string $dbPassword,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            appEnv: self::envString('APP_ENV', 'production'),
            appDebug: self::envBool('APP_DEBUG', false),

            dbConnection: self::envString('DB_CONNECTION', 'sqlite'),
            dbDatabase: self::envString('DB_DATABASE', ''),
            dbHost: self::envNullableString('DB_HOST'),
            dbPort: self::envNullableInt('DB_PORT'),
            dbUsername: self::envNullableString('DB_USERNAME'),
            dbPassword: self::envNullableString('DB_PASSWORD'),
        );
    }

    private static function envString(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function envNullableString(string $key): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function envBool(string $key, bool $default): bool
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1','true','yes','on'], true);
    }

    private static function envNullableInt(string $key): ?int
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }
}
