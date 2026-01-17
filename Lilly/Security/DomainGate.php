<?php
// src/Lilly/Security/DomainGate.php

declare(strict_types=1);

namespace Lilly\Security;

use Lilly\Http\Request;

final readonly class DomainGate
{
    public function __construct(
        private PolicyRegistry $policies,
        private GateRegistry $gates,
    ) {}

    public function authorizeDomains(Request $request, array $domains): PolicyDecision
    {
        foreach ($domains as $domain) {
            $policy = $this->policies->forDomain($domain);
            $decision = $policy->authorize($request);
            if (!$decision->allowed) {
                return $decision;
            }
        }

        return PolicyDecision::allow();
    }

    public function authorizeGates(Request $request, array $gateNames): PolicyDecision
    {
        foreach ($gateNames as $gateName) {
            $gate = $this->gates->byName($gateName);
            $decision = $gate->authorize($request);
            if (!$decision->allowed) {
                return $decision;
            }
        }

        return PolicyDecision::allow();
    }
}
