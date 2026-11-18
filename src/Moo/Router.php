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
                $key = $method . ' ' . $uri;
                $this->routes->set($key, $route);
            }
        }
        return $this;
    }

    public function dispatch(Request $request, Response $response): mixed
    {
        // Parse and match to the first route. The path is URL decoded here so
        // that route patterns describe decoded paths; parse first so an encoded
        // "?" or "#" is not mistaken for a query/fragment delimiter.
        $parsed = parse_url(preg_replace('#(/+)#', '/', $request->uri));
        $uri = isset($parsed['path']) && $parsed['path'] !== '' ? rawurldecode($parsed['path']) : '/';
        $uri = preg_replace('#(/+)#', '/', $uri);
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match("#^{$route->uri}$#", $uri, $matches)) {
                continue;
            }
            if ($route->method !== '*' && $route->method !== $request->method) {
                $allowed[$route->method] = true;
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

        if ($allowed) {
            $response->headers->set('Allow', implode(', ', array_keys($allowed)));
            throw new \RuntimeException(StatusCode::message(405), 405);
        }

        throw new \RuntimeException(StatusCode::message(404), 404);
    }

    public static function requestFactory(): Request
    {
        // Populate request with default values from $_SERVER.
        $request = new Request();
        $request->method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $request->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $request->server->populate($_SERVER);

        foreach ($_SERVER as $name => $value) {
            $key = $name;
            if (str_starts_with($key, 'REDIRECT_')) {
                $key = substr($key, 9);
            }
            if (str_starts_with($key, 'HTTP_')) {
                $request->headers->set(self::headerName(substr($key, 5)), $value);
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $request->headers->set(self::headerName($key), $value);
            }
        }

        $request->query->populate($_GET);
        $request->post->populate($_POST);
        $request->files->populate($_FILES);

        // Auto extract base URI of the front controller script. The script name
        // is only trusted up to the first ".php", so PATH_INFO and router
        // scripts (builtin server) do not eat the first URI segment.
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
        $pos = strpos($script, '.php');
        if ($pos !== false) {
            $dir = trim(str_replace('\\', '/', dirname(substr($script, 0, $pos + 4))), '/');
            $baseUri = $dir === '' || $dir === '.' ? '/' : '/' . $dir . '/';
            $request->baseUri = $baseUri;
            if ($baseUri !== '/') {
                $parsedUri = parse_url($request->uri);
                if (is_array($parsedUri) && isset($parsedUri['path']) && $parsedUri['path'] === rtrim($baseUri, '/')) {
                    $request->uri = '/' . (isset($parsedUri['query']) ? '?' . $parsedUri['query'] : '');
                } elseif (str_starts_with($request->uri, $baseUri)) {
                    $request->uri = '/' . substr($request->uri, strlen($baseUri));
                }
            }
        }

        return $request;
    }

    protected static function headerName(string $name): string
    {
        return ucwords(strtolower(str_replace('_', '-', $name)), '-');
    }

    protected static function assertPattern(string $uri): void
    {
        if (@preg_match("#^{$uri}$#", '') === false) {
            throw new \InvalidArgumentException("Invalid route pattern: {$uri}");
        }
    }
}
