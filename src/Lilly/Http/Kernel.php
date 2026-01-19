<?php
declare(strict_types=1);

namespace Lilly\Http;

use Lilly\Config\Config;
use Lilly\Database\Orm\OrmFactory;
use Lilly\Security\SecurityFactory;
use RuntimeException;

final class Kernel
{
    private Router $router;

    public function __construct(
        private readonly string $projectRoot,
        private readonly Config $config,
    ) {
        $this->router = new Router();
        $this->registerRoutes($this->router);
    }

    public function handle(Request $request): Response
    {
        try {
            $matched = $this->router->match($request);
            $route = $matched->route;

            foreach ($matched->params as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }
        } catch (RuntimeException $e) {
            return Response::text("404 Not Found\n" . $e->getMessage() . "\n", 404);
        }

        try {
            $gate = (new SecurityFactory(projectRoot: $this->projectRoot))->createDomainGate();

            $domainDecision = $gate->authorizeDomains($request, $route->domains);
            if (!$domainDecision->allowed) {
                return Response::text("403 Forbidden\n{$domainDecision->message}\n", $domainDecision->status);
            }

            $this->assertGatesAllowedForRoute($route);
            $gateDecision = $gate->authorizeGates($request, $route->gates);
            if (!$gateDecision->allowed) {
                return Response::text("403 Forbidden\n{$gateDecision->message}\n", $gateDecision->status);
            }

            $orm = (new OrmFactory(
                projectRoot: $this->projectRoot,
                config: $this->config,
            ))->create();

            $request = $request->withAttribute('orm', $orm);

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

        // No domain routes (auto-discovered)
        $this->registerAppComponentRoutes($router);

        // Cross-domain component routes (auto-discovered)
        $this->registerCrossComponentRoutes($router);
    }

    /**
     * Convention:
     * src/Domains/<Domain>/Routes/web.php
     * src/Domains/<Domain>/Routes/api.php
     * src/Domains/<Domain>/Routes/components.php
     *
     * Each file must return callable that accepts DomainRouter.
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

            if (str_starts_with($domain, '.')) {
                continue;
            }

            $domainDir = $domainsPath . '/' . $domain;
            if (!is_dir($domainDir)) {
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
     * src/App/Components/<Component>/Routes/web.php
     * src/App/Components/<Component>/Routes/api.php
     * src/App/Components/<Component>/Routes/components.php
     *
     * Each file must return callable that accepts Router.
     */
    private function registerAppComponentRoutes(Router $router): void
    {
        $root = $this->projectRoot . '/src/App/Components';

        if (!is_dir($root)) {
            return;
        }

        $components = scandir($root);
        if ($components === false) {
            return;
        }

        foreach ($components as $component) {
            if ($component === '.' || $component === '..') {
                continue;
            }

            if (str_starts_with($component, '.')) {
                continue;
            }

            $componentDir = $root . '/' . $component;
            if (!is_dir($componentDir)) {
                continue;
            }

            $routesDir = $componentDir . '/Routes';
            if (!is_dir($routesDir)) {
                continue;
            }

            foreach (['web.php', 'api.php', 'components.php'] as $file) {
                $path = $routesDir . '/' . $file;

                if (!is_file($path)) {
                    continue;
                }

                $registrar = require $path;

                if (!is_callable($registrar)) {
                    throw new RuntimeException("Route file must return callable: {$path}");
                }

                $appRouter = new AppRouter($router);
                $registrar($appRouter);
            }
        }
    }

    /**
     * Convention:
     * src/App/CrossComponents/<Group>/<Component>/Routes/web.php
     * src/App/CrossComponents/<Group>/<Component>/Routes/api.php
     * src/App/CrossComponents/<Group>/<Component>/Routes/components.php
     *
     * Group example: TeamsUsers
     * Domains are derived from the PascalCase group name by matching existing domain folders in src/Domains.
     *
     * Each file must return callable that accepts CrossDomainRouter.
     */
    private function registerCrossComponentRoutes(Router $router): void
    {
        $root = $this->projectRoot . '/src/App/CrossComponents';

        if (!is_dir($root)) {
            return;
        }

        $groups = scandir($root);
        if ($groups === false) {
            return;
        }

        $knownDomainKeys = $this->discoverKnownDomainKeys();

        foreach ($groups as $group) {
            if ($group === '.' || $group === '..') {
                continue;
            }

            if (str_starts_with($group, '.')) {
                continue;
            }

            $groupDir = $root . '/' . $group;
            if (!is_dir($groupDir)) {
                continue;
            }

            $domainKeys = $this->parseDomainKeysFromGroup($group, $knownDomainKeys);
            if ($domainKeys === []) {
                throw new RuntimeException("Invalid cross component group '{$group}'. Cannot derive domains from group name.");
            }

            $components = scandir($groupDir);
            if ($components === false) {
                continue;
            }

            foreach ($components as $component) {
                if ($component === '.' || $component === '..') {
                    continue;
                }

                if (str_starts_with($component, '.')) {
                    continue;
                }

                $componentDir = $groupDir . '/' . $component;
                if (!is_dir($componentDir)) {
                    continue;
                }

                $routesDir = $componentDir . '/Routes';
                if (!is_dir($routesDir)) {
                    continue;
                }

                $crossRouter = new CrossDomainRouter($router, $domainKeys);

                foreach (['web.php', 'api.php', 'components.php'] as $file) {
                    $path = $routesDir . '/' . $file;

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

    /**
     * @return list<string> lowercase domain keys derived from src/Domains folder names
     */
    private function discoverKnownDomainKeys(): array
    {
        $domainsPath = $this->projectRoot . '/src/Domains';

        if (!is_dir($domainsPath)) {
            return [];
        }

        $items = scandir($domainsPath);
        if ($items === false) {
            return [];
        }

        $keys = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (str_starts_with($item, '.')) {
                continue;
            }

            if (!is_dir($domainsPath . '/' . $item)) {
                continue;
            }

            $keys[] = strtolower($item);
        }

        $keys = array_values(array_unique($keys));

        usort(
            $keys,
            static fn (string $a, string $b): int => strlen($b) <=> strlen($a)
        );

        return $keys;
    }

    /**
     * Parses group name like "TeamsUsers" into ["teams", "users"]
     * by greedily matching known domain keys from src/Domains.
     *
     * @param list<string> $knownDomainKeys
     * @return list<string>
     */
    private function parseDomainKeysFromGroup(string $group, array $knownDomainKeys): array
    {
        $s = strtolower($group);
        $out = [];

        while ($s !== '') {
            $matched = false;

            foreach ($knownDomainKeys as $key) {
                if ($key === '') {
                    continue;
                }

                if (str_starts_with($s, $key)) {
                    $out[] = $key;
                    $s = substr($s, strlen($key));
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return [];
            }
        }

        return $out;
    }

    private function assertGatesAllowedForRoute(Route $route): void
    {
        foreach ($route->gates as $gate) {
            if ($gate === '') {
                throw new RuntimeException('Gate name may not be empty');
            }

            $prefix = $this->gateDomainPrefix($gate);

            // If you ever add "app.*" gates, they must never be used by domain routes.
            if ($prefix === 'app' && $route->domains !== []) {
                throw new RuntimeException("App gate '{$gate}' is not allowed on domain or cross-domain routes");
            }

            // Domain or cross-domain route: gate must match one of the route domains.
            // Examples:
            // - domains: ['users'] -> only users.*
            // - domains: ['teams','users'] -> teams.* or users.*
            if ($route->domains !== []) {
                if ($prefix === null || !in_array($prefix, $route->domains, true)) {
                    $allowed = implode(', ', $route->domains);
                    throw new RuntimeException("Gate '{$gate}' is not allowed for this route. Allowed domains: {$allowed}");
                }
            }
        }
    }

    private function gateDomainPrefix(string $gate): ?string
    {
        $pos = strpos($gate, '.');
        if ($pos === false || $pos === 0) {
            return null;
        }

        return substr($gate, 0, $pos);
    }

}
