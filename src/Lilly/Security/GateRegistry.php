<?php

declare(strict_types=1);

namespace Lilly\Security;

use RuntimeException;

final class GateRegistry
{
    /**
     * @var array<string, Gate>
     */
    private array $gatesByName = [];

    public function register(Gate $gate): void
    {
        $name = $gate->name();

        if ($name === '') {
            throw new RuntimeException('Gate name may not be empty');
        }

        $this->gatesByName[$name] = $gate;
    }

    public function byName(string $name): Gate
    {
        if (!isset($this->gatesByName[$name])) {
            throw new RuntimeException("No gate registered with name '{$name}'");
        }

        return $this->gatesByName[$name];
    }

    /**
     * @return array<string, Gate>
     */
    public function all(): array
    {
        return $this->gatesByName;
    }
}
