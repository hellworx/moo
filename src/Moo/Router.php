<?php

namespace Moo;

class Router
{
    public Collection $routes;

    public function __construct()
    {
        $this->routes = new Collection();
    }

    public function register(array $methods, array $uris, ?callable $callback): static
    {
        foreach ($methods as $method) {
            foreach ($uris as $uri) {
                self::assertPattern($uri);
                $route = new Route();
                $route->method = $method;
                $route->uri = $uri;
                $route->callback = $callback;
                $key = $method . ' ' . trim($uri, '/');
                $this->routes->set($key, $route);
            }
        }
        return $this;
    }

    public function dispatch(Request $request, Response $response): mixed
    {
        // Parse and match to the first route.
        $parsed = parse_url(preg_replace('#(/+)#', '/', $request->uri));
        $uri = isset($parsed['path']) ? $parsed['path'] : '/';
        foreach ($this->routes as $route) {
            if ($route->method != '*' && $route->method != $request->method) {
                continue;
            }
            if (!preg_match("#^{$route->uri}$#", $uri, $matches)) {
                continue;
            }
            if ($route->callback instanceof Moo) {
                return call_user_func($route->callback, $request, $response);
            }
            if (is_callable($route->callback)) {
                return call_user_func_array($route->callback, array_slice($matches, 1));
            }
            throw new \RuntimeException("Route {$route->method} {$route->uri} is not callable");
        }
        throw new \RuntimeException(StatusCode::message(404), 404);
    }

    public static function requestFactory(): Request
    {
        // Populate request with default values from $_SERVER.
        $request = new Request();
        $request->method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $request->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $request->headers->populate($_SERVER);
        $request->query->populate($_GET);
        $request->post->populate($_POST);
        $request->files->populate($_FILES);

        // Auto extract base URI of the front controller script. The script name
        // is only trusted up to the first ".php", so PATH_INFO and router
        // scripts (builtin server) do not eat the first URI segment.
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
        $pos = strpos($script, '.php');
        if ($pos !== false) {
            $baseUri = rtrim(dirname(substr($script, 0, $pos + 4)), '/') . '/';
            $request->baseUri = $baseUri;
            if ($baseUri !== '/' && str_starts_with($request->uri, $baseUri)) {
                $request->uri = '/' . substr($request->uri, strlen($baseUri));
            }
        }

        return $request;
    }

    protected static function assertPattern(string $uri): void
    {
        if (@preg_match("#^{$uri}$#", '') === false) {
            throw new \InvalidArgumentException("Invalid route pattern: {$uri}");
        }
    }
}
