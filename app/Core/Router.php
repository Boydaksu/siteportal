<?php

declare(strict_types=1);

namespace SitePortal\Core;

final class Router
{
    /** @var array<string, array<string, callable|array{class-string, string}>> */
    private array $routes = [];

    /** @var (callable(Request): Response)|null */
    private $notFoundHandler = null;

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function add(string $method, string $path, callable|array $handler): void
    {
        $upperMethod = strtoupper($method);
        $normalizedPath = $this->normalizePath($path);
        $this->routes[$upperMethod][$normalizedPath] = $handler;
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // 1. Direct match
        if (isset($this->routes[$method][$path])) {
            $response = $this->executeHandler($this->routes[$method][$path], $request);
            return $response->withRequestId($request->getRequestId());
        }

        // 2. Check if route exists for other HTTP methods (405 Method Not Allowed)
        $allowedMethods = [];
        foreach ($this->routes as $routeMethod => $paths) {
            if ($routeMethod !== $method && isset($paths[$path])) {
                $allowedMethods[] = $routeMethod;
            }
        }

        if (!empty($allowedMethods)) {
            $response = Response::text(
                '405 Method Not Allowed',
                405,
                ['Allow' => implode(', ', $allowedMethods)]
            );
            return $response->withRequestId($request->getRequestId());
        }

        // 3. Not Found (404)
        if ($this->notFoundHandler !== null) {
            $handler = $this->notFoundHandler;
            $response = $handler($request);
            return $response->withRequestId($request->getRequestId());
        }

        $response = Response::text('404 Not Found', 404);
        return $response->withRequestId($request->getRequestId());
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    private function executeHandler(callable|array $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            /** @var object $controller */
            $controller = new $class();
            /** @var Response $response */
            $response = $controller->$method($request);
            return $response;
        }

        /** @var Response $response */
        $response = $handler($request);
        return $response;
    }

    private function normalizePath(string $path): string
    {
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }
}
