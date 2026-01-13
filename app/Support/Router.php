<?php
declare(strict_types=1);

namespace App\Support;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, string $handler, array $middleware = []): void
    {
        $this->map('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, string $handler, array $middleware = []): void
    {
        $this->map('POST', $pattern, $handler, $middleware);
    }

    private function map(string $method, string $pattern, string $handler, array $middleware): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }

                $this->runMiddleware($route['middleware']);

                [$class, $action] = explode('@', $route['handler'], 2);
                $controller = new $class();
                $controller->$action($params);
                return;
            }
        }

        http_response_code(404);
        echo "Seite nicht gefunden.";
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $mw) {
            if ($mw === 'auth') {
                (new AuthMiddleware())->handle();
                continue;
            }
            if (str_starts_with($mw, 'role:')) {
                $roles = explode(',', substr($mw, 5));
                (new RoleMiddleware($roles))->handle();
                continue;
            }
        }
    }
}
