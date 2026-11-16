<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\MethodNotAllowedException;
use App\Core\Exceptions\NotFoundException;

class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     regex: string,
     *     paramNames: string[],
     *     handler: callable|array{0: class-string, 1: string}
     * }>
     */
    protected array $routes = [];

    public function addRoute(string $method, string $path, callable|array $handler): self
    {
        $normalizedPath = Request::normalizePath($path);
        [$regex, $paramNames] = self::compileRoute($normalizedPath);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $normalizedPath,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'handler' => $handler,
        ];

        return $this;
    }

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, callable|array $handler): self
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    /**
     * Match a request against registered routes.
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     * @return array{handler: callable|array, params: array<string, string>}
     */
    public function match(Request $request): array
    {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();

        $matchingPathFound = false;
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $requestPath, $matches)) {
                $matchingPathFound = true;
                $allowedMethods[] = $route['method'];

                if ($route['method'] === $requestMethod) {
                    $params = [];
                    foreach ($route['paramNames'] as $paramName) {
                        if (isset($matches[$paramName])) {
                            $params[$paramName] = $matches[$paramName];
                        }
                    }

                    $request->setRouteParams($params);

                    return [
                        'handler' => $route['handler'],
                        'params' => $params,
                    ];
                }
            }
        }

        if ($matchingPathFound) {
            $uniqueMethods = array_values(array_unique($allowedMethods));
            throw new MethodNotAllowedException(
                $uniqueMethods,
                "HTTP method {$requestMethod} is not supported for path {$requestPath}"
            );
        }

        throw new NotFoundException("Route not found: {$requestMethod} {$requestPath}");
    }

    /**
     * Compile a path with parameters (e.g. /games/{id}) into a regex.
     *
     * @return array{0: string, 1: string[]}
     */
    protected static function compileRoute(string $path): array
    {
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $path);

        $regex = '#^' . $pattern . '$#u';

        return [$regex, $paramNames];
    }
}
