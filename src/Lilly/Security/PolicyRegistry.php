<?php

declare(strict_types=1);

namespace Lilly\Security;

use RuntimeException;

final class PolicyRegistry
{
    /**
     * @var array<string, DomainPolicy>
     */
    private array $policiesByDomain = [];

    public function register(DomainPolicy $policy): void
    {
        $domain = $policy->domain();

        if ($domain === '') {
            throw new RuntimeException('Policy domain key may not be empty');
        }

        $this->policiesByDomain[$domain] = $policy;
    }

    public function forDomain(string $domain): DomainPolicy
    {
        if (!isset($this->policiesByDomain[$domain])) {
            throw new RuntimeException("No policy registered for domain '{$domain}'");
        }

        return $this->policiesByDomain[$domain];
    }

    /**
     * @return array<string, DomainPolicy>
     */
    public function all(): array
    {
        return $this->policiesByDomain;
    }
}
