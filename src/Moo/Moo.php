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
            $code = StatusCode::normalize($exc->getCode());

            // The router sets Allow for 405 responses; keep it across the reset.
            $allow = $code === StatusCode::HTTP_METHOD_NOT_ALLOWED
                ? $this->response->headers->get('Allow')
                : null;

            $this->response->setCode($code);
            $this->response->headers->clear();
            if ($allow !== null) {
                $this->response->headers->set('Allow', $allow);
            }
            $this->response->body = $exc->getMessage();
        };

        // Default flush handler.
        $this->flush = function () {
            if (!headers_sent()) {
                http_response_code($this->response->code);
                foreach ($this->response->headers as $name => $value) {
                    header($name . ': ' . $value, true);
                }
            }
            if ($this->request->method !== 'HEAD' && !in_array($this->response->code, [204, 304], true)) {
                $body = $this->response->body;
                if ($body !== null && !is_scalar($body) && !$body instanceof \Stringable) {
                    throw new \UnexpectedValueException('Response body must be a string or stringable');
                }
                echo (string) $body;
            }
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

        ob_start();
        self::$depth++;
        try {
            $this->invokeHook('before');

            $result = $this->router->dispatch($this->request, $this->response);
            if ($result !== null) {
                $this->response->body = $result;
            }

            $this->invokeHook('after');
        } catch (\Throwable $exc) {
            if ($this->hasHook('error')) {
                $this->invokeHook('error', [$exc]);
            } else {
                throw $exc;
            }
        } finally {
            $output = ob_get_clean();
            self::$depth--;

            // Append buffered output only when there is some, so a handler can
            // still return non-string values (serialized later in after).
            if (is_string($output) && $output !== '') {
                $body = $this->response->body;
                if ($body !== null && !is_scalar($body) && !$body instanceof \Stringable) {
                    throw new \UnexpectedValueException('Cannot append buffered output to a non-string response body');
                }
                $this->response->body = (string) $body . $output;
            }
        }

        return self::$depth === 0 && $this->hasHook('flush') ? $this->invokeHook('flush') : null;
    }

    protected function hasHook(string $name): bool
    {
        return method_exists($this, $name) || isset($this->{$name});
    }

    protected function invokeHook(string $name, array $args = []): mixed
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$args);
        }

        if (!isset($this->{$name})) {
            return null;
        }
        $hook = $this->{$name};
        if (!is_callable($hook)) {
            throw new \InvalidArgumentException("Hook {$name} is not callable");
        }
        return $hook(...$args);
    }

    public function __invoke(?Request $request = null, ?Response $response = null): mixed
    {
        return $this->_dispatch($request, $response);
    }
}
