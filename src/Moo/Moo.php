<?php

namespace Moo;

class Moo extends Extendable
{
    public Request $request;
    public Response $response;
    public Router $router;

    public mixed $before = null;
    public mixed $after = null;
    public mixed $error = null;
    public mixed $flush = null;

    /**
     * Number of dispatches currently on the stack, shared by all instances so
     * that only the outermost dispatch flushes the response.
     */
    protected static int $depth = 0;

    public function __construct()
    {
        $this->router = new Router();

        // Default pre request hook.
        $this->before = function () {};

        // Default post request hook.
        $this->after = function () {};

        // Default error handler.
        $this->error = function (\Throwable $exc) {
            $code = $exc->getCode();
            $code = is_int($code) && $code >= 400 && $code <= 599 ? $code : 500;
            $this->response->code = $code;
            $this->response->message = StatusCode::message($code);
            $this->response->headers->clear();
            $this->response->body = $exc->getMessage();
        };

        // Default flush handler.
        $this->flush = function () {
            header('HTTP/1.1 ' . $this->response->code . ' ' . $this->response->message);
            foreach ($this->response->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            echo $this->response->body;
        };
    }

    public function route(string $uri, ?callable $callback): static
    {
        $this->router->register(['*'], [$uri], $callback);
        return $this;
    }

    public function get(string $uri, ?callable $callback): static
    {
        $this->router->register(['GET'], [$uri], $callback);
        return $this;
    }

    public function head(string $uri, ?callable $callback): static
    {
        $this->router->register(['HEAD'], [$uri], $callback);
        return $this;
    }

    public function post(string $uri, ?callable $callback): static
    {
        $this->router->register(['POST'], [$uri], $callback);
        return $this;
    }

    public function put(string $uri, ?callable $callback): static
    {
        $this->router->register(['PUT'], [$uri], $callback);
        return $this;
    }

    public function delete(string $uri, ?callable $callback): static
    {
        $this->router->register(['DELETE'], [$uri], $callback);
        return $this;
    }

    public function connect(string $uri, ?callable $callback): static
    {
        $this->router->register(['CONNECT'], [$uri], $callback);
        return $this;
    }

    public function options(string $uri, ?callable $callback): static
    {
        $this->router->register(['OPTIONS'], [$uri], $callback);
        return $this;
    }

    public function trace(string $uri, ?callable $callback): static
    {
        $this->router->register(['TRACE'], [$uri], $callback);
        return $this;
    }

    public function patch(string $uri, ?callable $callback): static
    {
        $this->router->register(['PATCH'], [$uri], $callback);
        return $this;
    }

    protected function _dispatch(?Request $request = null, ?Response $response = null): mixed
    {
        $this->request = isset($request) ? $request : $this->router->requestFactory();
        $this->response = isset($response) ? $response : new Response();

        self::$depth++;
        ob_start();
        try {
            is_callable($this->before) ? $this->before() : null;

            $result = $this->router->dispatch($this->request, $this->response);
            if ($result !== null) {
                $this->response->body = $result;
            }

            is_callable($this->after) ? $this->after() : null;
        } catch (\Throwable $exc) {
            is_callable($this->error) ? $this->error($exc) : throw $exc;
        } finally {
            $output = ob_get_clean();
            $this->response->body = $this->response->body . (is_string($output) ? $output : '');
            self::$depth--;
        }

        return self::$depth === 0 && is_callable($this->flush) ? $this->flush() : null;
    }

    public function __invoke(?Request $request = null, ?Response $response = null): mixed
    {
        return $this->_dispatch($request, $response);
    }
}
