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
        // HEAD (uptime monitors) is answered as GET; the web server leaves out the body. Request::method() stays HEAD,
        // so a HEAD is not counted as a website visit.
        foreach ($this->routes[$method === 'HEAD' ? 'GET' : $method] ?? [] as [$regex, $handler]) {
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                Request::$routeParams = $params;
                [$class, $action] = $handler;
                if (!class_exists($class) || !method_exists($class, $action)) {
                    Response::notFound(); // a section that is not installed yet
                }
                $controller = new $class();
                $controller->$action(...array_values($params));
                return;
            }
        }
        // no page here: an address the old website, other sites or bookmarks still use goes on to its new page (301)
        if (in_array($method, ['GET', 'HEAD'], true) && !str_starts_with($path, '/portal') && ($to = \App\Services\LegacyRedirects::find($path)) !== null) {
            Response::redirect($to, 301);
        }
        Response::notFound();
    }
}
