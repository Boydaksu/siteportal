<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SitePortal\Controllers\HealthController;
use SitePortal\Core\Request;
use SitePortal\Core\Response;
use SitePortal\Core\Router;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testDispatchesGetRouteWithClosure(): void
    {
        $this->router->get('/ping', function (Request $req): Response {
            return Response::text('pong');
        });

        $request = Request::create('GET', '/ping');
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('pong', $response->getBody());
        self::assertSame($request->getRequestId(), $response->getHeader('X-Request-Id'));
    }

    public function testDispatchesControllerAction(): void
    {
        $this->router->get('/health', [HealthController::class, 'health']);

        $request = Request::create('GET', '/health');
        $response = $this->router->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"status": "ok"', $response->getBody());
    }

    public function testReturns405MethodNotAllowed(): void
    {
        $this->router->get('/only-get', function (Request $req): Response {
            return Response::text('ok');
        });

        $request = Request::create('POST', '/only-get');
        $response = $this->router->dispatch($request);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeader('Allow'));
    }

    public function testDefault404WhenRouteNotFound(): void
    {
        $request = Request::create('GET', '/not-found');
        $response = $this->router->dispatch($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('404 Not Found', $response->getBody());
    }

    public function testCustomNotFoundHandler(): void
    {
        $this->router->setNotFoundHandler(function (Request $req): Response {
            return Response::json(['error' => 'sayfa bulunamadi'], 404);
        });

        $request = Request::create('GET', '/missing');
        $response = $this->router->dispatch($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('sayfa bulunamadi', $response->getBody());
    }

    public function testDispatchesParameterizedRouteAndExtractsParameters(): void
    {
        $this->router->get('/items/{id}', function (Request $req): Response {
            $id = $req->getRouteParam('id');
            return Response::json(['item_id' => $id]);
        });

        $this->router->get('/items/{id}/details', function (Request $req): Response {
            $id = $req->getRouteParam('id');
            return Response::json(['item_id' => $id, 'details' => true]);
        });

        $request1 = Request::create('GET', '/items/item_123');
        $response1 = $this->router->dispatch($request1);
        self::assertSame(200, $response1->getStatusCode());
        self::assertStringContainsString('"item_id": "item_123"', $response1->getBody());

        $request2 = Request::create('GET', '/items/item_123/details');
        $response2 = $this->router->dispatch($request2);
        self::assertSame(200, $response2->getStatusCode());
        self::assertStringContainsString('"details": true', $response2->getBody());

        // 405 for parameterized route
        $requestPost = Request::create('POST', '/items/item_123');
        $responsePost = $this->router->dispatch($requestPost);
        self::assertSame(405, $responsePost->getStatusCode());
    }
}
