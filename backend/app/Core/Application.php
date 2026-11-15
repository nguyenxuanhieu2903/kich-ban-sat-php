<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ExceptionHandler;
use App\Core\Middleware\MiddlewareInterface;
use Throwable;

class Application
{
    protected Router $router;
    /** @var MiddlewareInterface[] */
    protected array $middleware = [];
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->router = new Router();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Dispatch an incoming Request through middleware pipeline and Router.
     */
    public function dispatch(Request $request): Response
    {
        try {
            return $this->runPipeline($request);
        } catch (Throwable $e) {
            return ExceptionHandler::handle($e, $request);
        }
    }

    /**
     * Execute the middleware pipeline and resolve the route handler.
     */
    protected function runPipeline(Request $request): Response
    {
        $middlewareStack = $this->middleware;

        $corePipeline = function (Request $req): Response {
            $match = $this->router->match($req);
            $handler = $match['handler'];
            $params = $match['params'];

            return $this->invokeHandler($handler, $req, $params);
        };

        $pipeline = array_reduce(
            array_reverse($middlewareStack),
            function (callable $next, MiddlewareInterface $middleware) {
                return function (Request $req) use ($middleware, $next): Response {
                    return $middleware->handle($req, $next);
                };
            },
            $corePipeline
        );

        return $pipeline($request);
    }

    /**
     * Invoke the matched route handler.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<string, string> $params
     */
    protected function invokeHandler(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $class = $handler[0];
            $method = $handler[1];
            $instance = new $class();
            $result = $instance->$method($request, ...array_values($params));
        } else {
            $result = call_user_func($handler, $request, ...array_values($params));
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return new Response($result);
        }

        return Response::json($result);
    }
}
