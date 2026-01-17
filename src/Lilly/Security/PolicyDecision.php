<?php

// src/Lilly/Security/PolicyDecision.php

declare(strict_types=1);

namespace Lilly\Security;

final readonly class PolicyDecision
{
    private function __construct(
        public bool   $allowed,
        public int    $status,
        public string $message,
    ) {
    }

    public static function allow(): self
    {
        return new self(true, 200, 'OK');
    }

    public static function deny(int $status = 403, string $message = 'Forbidden'): self
    {
        return new self(false, $status, $message);
    }
}
