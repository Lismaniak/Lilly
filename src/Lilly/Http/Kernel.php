<?php
declare(strict_types=1);

namespace Lilly\Http;

use Lilly\Security\SecurityFactory;
use RuntimeException;

final class Kernel
{
    private Router $router;

    public function __construct(
        private readonly string $projectRoot
    ) {
        $this->router = new Router();

        $this->registerRoutes($this->router);
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->match($request);
        } catch (RuntimeException $e) {
            return Response::text("404 Not Found\n" . $e->getMessage() . "\n", 404);
        }

        try {
            $gate = (new SecurityFactory(projectRoot: $this->projectRoot))->createDomainGate();

            $domainDecision = $gate->authorizeDomains($request, $route->domains);
            if (!$domainDecision->allowed) {
                return Response::text("403 Forbidden\n{$domainDecision->message}\n", $domainDecision->status);
            }

            $gateDecision = $gate->authorizeGates($request, $route->gates);
            if (!$gateDecision->allowed) {
                return Response::text("403 Forbidden\n{$gateDecision->message}\n", $gateDecision->status);
            }

            $handler = $route->handler;
            $result = $handler($request);

            if ($result instanceof Response) {
                return $result;
            }

            return Response::text((string) $result);
        } catch (RuntimeException $e) {
            return Response::text("500 Internal Server Error\n" . $e->getMessage() . "\n", 500);
        }
    }

    private function registerRoutes(Router $router): void
    {
        // Core routes (framework-level, not domain-owned)
        $router->get(
            path: '/health',
            handler: fn () => Response::json(['ok' => true]),
            domains: [],
            gates: [],
        );

        // Domain routes (auto-discovered)
        $this->registerDomainRoutes($router);
        $this->registerCrossComponentRoutes($router);
    }

    /**
     * Convention:
     * src/Domains/<Domain>/Routes/web.php
     * src/Domains/<Domain>/Routes/api.php
     * src/Domains/<Domain>/Routes/components.php
     *
     * Each file must "return callable" that accepts DomainRouter.
     */
    private function registerDomainRoutes(Router $router): void
    {
        $domainsPath = $this->projectRoot . '/src/Domains';

        if (!is_dir($domainsPath)) {
            return;
        }

        $items = scandir($domainsPath);
        if ($items === false) {
            return;
        }

        foreach ($items as $domain) {
            if ($domain === '.' || $domain === '..') {
                continue;
            }

            $domainDir = $domainsPath . '/' . $domain;

            if (!is_dir($domainDir)) {
                continue;
            }

            if (str_starts_with($domain, '.')) {
                continue;
            }

            $domainKey = strtolower($domain);
            $domainRouter = new DomainRouter($router, $domainKey);

            foreach (['web.php', 'api.php', 'components.php'] as $file) {
                $path = $domainDir . '/Routes/' . $file;

                if (!is_file($path)) {
                    continue;
                }

                $registrar = require $path;

                if (!is_callable($registrar)) {
                    throw new RuntimeException("Route file must return callable: {$path}");
                }

                $registrar($domainRouter);
            }
        }
    }

    /**
     * Convention:
     * src/UI/CrossComponents/<signature>/Routes/web.php
     * src/UI/CrossComponents/<signature>/Routes/api.php
     * src/UI/CrossComponents/<signature>/Routes/components.php
     *
     * signature example:
     * teams+users
     *
     * Each file must "return callable" that accepts CrossDomainRouter.
     */
    private function registerCrossComponentRoutes(Router $router): void
    {
        $root = $this->projectRoot . '/src/UI/CrossComponents';

        if (!is_dir($root)) {
            return;
        }

        $items = scandir($root);
        if ($items === false) {
            return;
        }

        foreach ($items as $signature) {
            if ($signature === '.' || $signature === '..') {
                continue;
            }

            if (str_starts_with($signature, '.')) {
                continue;
            }

            $signatureDir = $root . '/' . $signature;
            if (!is_dir($signatureDir)) {
                continue;
            }

            $domainKeys = array_values(array_filter(explode('+', strtolower($signature))));
            if ($domainKeys === []) {
                continue;
            }

            $crossRouter = new CrossDomainRouter($router, $domainKeys);

            foreach (['web.php', 'api.php', 'components.php'] as $file) {
                $path = $signatureDir . '/Routes/' . $file;

                if (!is_file($path)) {
                    continue;
                }

                $registrar = require $path;

                if (!is_callable($registrar)) {
                    throw new RuntimeException("Route file must return callable: {$path}");
                }

                $registrar($crossRouter);
            }
        }
    }
}
