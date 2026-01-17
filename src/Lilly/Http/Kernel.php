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
        $router->get(
            path: '/health',
            handler: fn () => Response::json(['ok' => true]),
            domains: [],
            gates: [],
        );

        $router->get(
            path: '/users',
            handler: fn () => Response::text("Users index\n"),
            domains: ['users'],
            gates: ['users.view'],
        );

        $router->post(
            path: '/users/invite',
            handler: fn () => Response::text("Invited\n"),
            domains: ['users'],
            gates: ['users.invite'],
        );
    }
}
