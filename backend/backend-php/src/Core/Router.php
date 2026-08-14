<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal method + path router.
 *
 * Route paths may contain {param} placeholders, e.g. "/schools/{id}/students".
 * Handlers are [ControllerClass::class, 'method'] callables, optionally
 * preceded by an array of middleware callables that run before it.
 *
 * A middleware is any callable(Request $req): void — it should call
 * Response::fail()/unauthorized()/etc (which exit()s) to short-circuit,
 * or simply return to let the chain continue.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:array<int,string>, middleware: array<int,callable>, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, callable $handler, array $middleware): void
    {
        $pattern = rtrim($pattern, '/');
        if ($pattern === '') {
            $pattern = '/';
        }

        $paramNames = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $paramNames,
            'middleware' => $middleware,
            'handler'    => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        // CORS preflight is answered before any routing / auth happens.
        if ($method === 'OPTIONS') {
            Response::send204();
        }

        $matchedPathButNotMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $matchedPathButNotMethod = true;
                continue;
            }

            array_shift($matches); // drop full match
            $params = array_combine($route['params'], $matches) ?: [];
            $request->setParams($params);

            foreach ($route['middleware'] as $middleware) {
                $middleware($request);
            }

            /** @var callable $handler */
            $handler = $route['handler'];
            $handler($request);
            return;
        }

        if ($matchedPathButNotMethod) {
            Response::fail('Method not allowed', 405);
        }

        Response::notFound('Route not found');
    }
}
