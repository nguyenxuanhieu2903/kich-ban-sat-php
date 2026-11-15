<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\MethodNotAllowedException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    public function testMatchesValidRoute(): void
    {
        $this->router->get('/api/games', function (Request $request) {
            return Response::json(['games' => []]);
        });

        $request = new Request('GET', '/api/games');
        $match = $this->router->match($request);

        $this->assertIsCallable($match['handler']);
        $this->assertEmpty($match['params']);
    }

    public function testThrowsNotFoundForUnknownRoute(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $this->router->get('/api/games', fn() => Response::json([]));
        $request = new Request('GET', '/api/non-existent');

        $this->router->match($request);
    }

    public function testThrowsMethodNotAllowedWhenPathExistsForDifferentMethod(): void
    {
        $this->router->get('/api/games', fn() => Response::json([]));
        $request = new Request('POST', '/api/games');

        try {
            $this->router->match($request);
            $this->fail('Expected MethodNotAllowedException was not thrown');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertSame('GET', $e->getHeaders()['Allow']);
        }
    }

    public function testMatchesRouteWithParameters(): void
    {
        $this->router->get('/api/games/{id}', function (Request $request, string $id) {
            return Response::json(['id' => $id]);
        });

        $request = new Request('GET', '/api/games/game-123');
        $match = $this->router->match($request);

        $this->assertSame(['id' => 'game-123'], $match['params']);
        $this->assertSame('game-123', $request->param('id'));
    }

    public function testMatchesRouteWithMultipleParameters(): void
    {
        $this->router->get('/api/rooms/{roomId}/participants/{userId}', function (Request $request) {
            return Response::json([]);
        });

        $request = new Request('GET', '/api/rooms/room-99/participants/user-42');
        $match = $this->router->match($request);

        $this->assertSame('room-99', $match['params']['roomId']);
        $this->assertSame('user-42', $match['params']['userId']);
    }
}
