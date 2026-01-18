<?php

declare(strict_types=1);

namespace Lilly\Security;

use RuntimeException;

final class SecurityFactory
{
    public function __construct(
        private readonly string $projectRoot
    ) {}

    /**
     * Builds registries and returns a ready-to-use DomainGate.
     *
     * Enforced:
     * - every domain must have Domains\<Domain>\Policies\<Domain>Policy
     * - missing policy throws
     */
    public function createDomainGate(): DomainGate
    {
        $policies = new PolicyRegistry();
        $gates = new GateRegistry();

        foreach ($this->discoverDomainNames() as $domain) {
            $policyFqcn = "Domains\\{$domain}\\Policies\\{$domain}Policy";

            if (!class_exists($policyFqcn)) {
                throw new RuntimeException("Missing policy class {$policyFqcn}");
            }

            $policy = new $policyFqcn();

            if (!$policy instanceof DomainPolicy) {
                throw new RuntimeException("{$policyFqcn} must implement " . DomainPolicy::class);
            }

            $policies->register($policy);

            foreach ($this->discoverGateClassesForDomain($domain) as $gateFqcn) {
                if (!class_exists($gateFqcn)) {
                    continue;
                }

                $gate = new $gateFqcn();

                if (!$gate instanceof Gate) {
                    throw new RuntimeException("{$gateFqcn} must implement " . Gate::class);
                }

                $gates->register($gate);
            }
        }

        $this->registerAppPolicyAndGates($policies, $gates);
        return new DomainGate($policies, $gates);
    }

    /**
     * @return list<string> Domain folder names, eg ["Users", "Teams"]
     */
    private function discoverDomainNames(): array
    {
        $domainsPath = $this->projectRoot . '/src/Domains';

        if (!is_dir($domainsPath)) {
            return [];
        }

        $items = scandir($domainsPath);
        if ($items === false) {
            return [];
        }

        $domains = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $domainsPath . '/' . $item;

            if (!is_dir($full)) {
                continue;
            }

            if (str_starts_with($item, '.')) {
                continue;
            }

            $domains[] = $item;
        }

        sort($domains);

        return $domains;
    }

    /**
     * Convention:
     * src/Domains/<Domain>/Policies/Gates/*.php
     * maps to:
     * Domains\<Domain>\Policies\Gates\<FilenameWithoutPhp>
     *
     * @return list<class-string>
     */
    private function discoverGateClassesForDomain(string $domain): array
    {
        $gateDir = $this->projectRoot . "/src/Domains/{$domain}/Policies/Gates";

        if (!is_dir($gateDir)) {
            return [];
        }

        $files = glob($gateDir . '/*.php');
        if ($files === false) {
            return [];
        }

        $classes = [];

        foreach ($files as $file) {
            $base = basename($file, '.php');

            if ($base === '') {
                continue;
            }

            $classes[] = "Domains\\{$domain}\\Policies\\Gates\\{$base}";
        }

        sort($classes);

        return $classes;
    }

    private function registerAppPolicyAndGates(PolicyRegistry $policies, GateRegistry $gates): void
    {
        $appRoot = $this->projectRoot . '/src/App';

        if (!is_dir($appRoot)) {
            return;
        }

        $policyFqcn = 'App\\Policies\\AppPolicy';

        // App policy is optional. If missing, App routes can still exist.
        if (class_exists($policyFqcn)) {
            $policy = new $policyFqcn();

            if (!$policy instanceof DomainPolicy) {
                throw new RuntimeException("{$policyFqcn} must implement " . DomainPolicy::class);
            }

            $policies->register($policy);
        }

        foreach ($this->discoverAppGateClasses() as $gateFqcn) {
            if (!class_exists($gateFqcn)) {
                continue;
            }

            $gate = new $gateFqcn();

            if (!$gate instanceof Gate) {
                throw new RuntimeException("{$gateFqcn} must implement " . Gate::class);
            }

            $gates->register($gate);
        }
    }

    /**
     * Convention:
     * src/App/Policies/Gates/*.php
     * maps to:
     * App\Policies\Gates\<FilenameWithoutPhp>
     *
     * @return list<class-string>
     */
    private function discoverAppGateClasses(): array
    {
        $gateDir = $this->projectRoot . '/src/App/Policies/Gates';

        if (!is_dir($gateDir)) {
            return [];
        }

        $files = glob($gateDir . '/*.php');
        if ($files === false) {
            return [];
        }

        $classes = [];

        foreach ($files as $file) {
            $base = basename($file, '.php');

            if ($base === '') {
                continue;
            }

            $classes[] = "App\\Policies\\Gates\\{$base}";
        }

        sort($classes);

        return $classes;
    }
}
