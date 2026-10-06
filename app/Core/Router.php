<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router. Route patterns use {name} placeholders; {id} only matches digits.
 * Handlers are [ControllerClass, 'method'] pairs.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($m) {
            return match ($m[1]) {
                'id', 'id2' => '(?P<' . $m[1] . '>\d+)',
                'lang'      => '(?P<lang>en|ar)',
                'path'      => '(?P<path>.+)',
                default     => '(?P<' . $m[1] . '>[^/]+)',
            };
        }, $pattern);
        $this->routes[$method][] = ['#^' . $regex . '$#u', $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes[$method] ?? [] as [$regex, $handler]) {
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                Request::$routeParams = $params;
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->$action(...array_values($params));
                return;
            }
        }
        Response::notFound();
    }
}
