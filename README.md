# Moo

Moo is a small, dependency free PHP front controller. It gives you regex based
routing, request and response models, a predictable request lifecycle and native
PHP templating in a handful of classes, without pulling in a framework.

It is meant for the cases where raw `.php` scripts would otherwise be the answer,
but you still want routing, hooks and testable handlers. If you need an
ecosystem, use Symfony or Laravel; Moo deliberately has none.

## Requirements

- PHP 8.0 or newer, below 9.0 (the code is written to run on 8.0 through 8.5)
- No runtime dependencies

Development tooling (PHPUnit 10.5, or 11.5 on PHP 8.2+) requires PHP 8.1 or newer.

## Installation

~~~
composer config minimum-stability dev
composer require hellworx/moo:dev-main
~~~

## Hello World

Simplest Moo application.

~~~php
<?php
$moo = new Moo\Moo();

$moo->get('/', function () {
    return "Hello, this is Moo!";
});

$moo();
~~~

## Usage

This sample shows state, plugins and parametrized routing.

~~~php
<?php
$moo = new Moo\Moo();

// Use moo as service container, inject dependency.
$moo->bookService = new App\BookService();

// Override the before hook to set Content-Type header.
$moo->before = function () use ($moo) {
    $moo->response->headers->set('Content-Type', 'application/json');
};

// Override the after hook to serialize output to JSON.
$moo->after = function () use ($moo) {
    $moo->response->body = json_encode($moo->response->body);
};

// Custom plugin.
$moo->findBookById = function ($bookId) use ($moo) {
    return $moo->bookService->find($bookId);
};

// Define index handler.
$moo->get('/', function () {
    echo "Hello, this is Moo bookstore!";
});

// Define `GET /books/<id>` handler.
$moo->get('/books/(\d+)', function ($bookId) use ($moo) {
    return $moo->findBookById($bookId);
});

// Run Moo.
$moo();
~~~

## Templates

Moo supports native PHP templates, however you can extend the base class and render templates with any other engine.
See the `website` example in `examples`.

~~~php
use Moo\Moo;
use Moo\Template;

$moo = new Moo();

// Create template renderer.
$moo->template = new Template(__DIR__ . '/templates', [
    'foo' => 'bar'
]);

// Custom template plugin.
$moo->template->date = function () {
    return date('Y-m-d');
};

// Index page handler.
$moo->get('/', function () use ($moo) {
    return $moo->template->render('index.phtml', [
        'hello' => 'world'
    ]);
});

~~~

Sample PHP template code.

~~~html
<div>foo = <?php echo $foo ?></div>
<div>hello = <?php echo $hello ?></div>
<div>date = <?php echo $this->date() ?></div>
~~~

Templates are confined to the lookup directory, partials are included relative
to the template that includes them, and template context cannot override the
renderer internals.

## Classy Moo

Some may prefer a class-based style; if you like that, try the following.

~~~php
<?php

use Moo\Moo;

class Application extends Moo
{
    public function __construct()
    {
        parent::__construct();
        $this->get('/', [$this, 'index']);
    }

    public function index()
    {
        echo 'Hello World';
    }
}

$app = new Application();
$app();
~~~

Hooks can be overridden as methods too, they take precedence over properties.

Test it and make your colleagues happy.

~~~php
<?php

use PHPUnit\Framework\TestCase;
use Moo\Request;

class ApplicationTest extends TestCase
{
    public function testIndex()
    {
        $request = new Request();
        $request->method = 'GET';
        $request->uri = '/';

        $app = new Application();
        $app->flush = null;     // You don't want output buffer flush in the unit tests.
        $app($request);

        $this->assertEquals(200, $app->response->code);
        $this->assertEquals('Hello World', $app->response->body);
    }
}
~~~

## Nesting

You can actually use one instance of Moo as a callback in another Moo.

~~~php
<?php

use Moo\Moo;

$usersMoo = new Moo();
$usersMoo->get('/users/(\d+)', function ($id) {
    // ...
});

$booksMoo = new Moo();
$booksMoo->get('/books/(\d+)', function ($id) {
    // ...
});

$moo = new Moo();
$moo->route('/users/.*', $usersMoo);
$moo->route('/books/.*', $booksMoo);

$moo();
~~~

Only the outermost dispatch flushes, so the output from nested apps is sent once.

Nested apps receive the outer `Request` and `Response`, so an error inside the
inner app is reflected in the shared response. Each level runs its own lifecycle,
including `after`: run serialization on only one level, otherwise the body is
encoded twice. Nest through `route()` (or pass the response explicitly); calling a
nested app without the outer response discards its output.

## Examples

There are some examples in the `examples` directory.
To run them you can use the built-in PHP server.

~~~
php -S 0.0.0.0:8080 examples/hello-world/index.php
~~~

## Documentation

The goal of Moo is simplicity, flexibility and ease of use.

### Concepts

Moo is written in PHP and is closure based. From design perspective it is a front controller, the `Moo\Moo` class exposes a set of standard HTTP methods to bind routing handlers as closures. Additionally, Moo acts as a state container and can be extended with plugins.

All Moo components reside in the PSR-4 `Moo` namespace. The main component is the
`Moo\Moo` class. There are three models (`Moo\Request`, `Moo\Response`,
`Moo\Route`), the `Moo\Router` dispatcher, and the supporting `Moo\Model`,
`Moo\Collection`, `Moo\Template` and `Moo\StatusCode` classes.

Moo does output buffering, so you can echo or return a value from a handler. A
returned value becomes the response body; Moo never serializes it automatically,
so encode arrays and objects in the `after` hook.

`Moo\Model` and `Moo\Collection` provide the small amount of data plumbing Moo
needs, so the library stays dependency free.

### Lifecycle

Here is what happens when you call `$moo(...)`:

```mermaid
flowchart TD
    invoke["$moo()"] --> before["before hook"]
    before --> dispatch["match route"]
    dispatch --> |handler result| after["after hook"]
    dispatch --> |throwable or no match| error["error handler"]
    error --> append["append buffered output"]
    after --> append
    append --> flush["flush (outermost dispatch only)"]
```

The response body is whatever the handler returned. Output produced with `echo`
is buffered and appended to the body after the `after` hook ran. When nothing was
echoed, the returned value is preserved as-is; when output exists, the body must
be stringable. This is why arrays and objects should be serialized in `after`.

### Routing

You can bind handlers to standard HTTP methods:
- GET: `$moo->get(...)`
- HEAD: `$moo->head(...)`
- POST: `$moo->post(...)`
- PUT:  `$moo->put(...)`
- DELETE: `$moo->delete(...)`
- CONNECT: `$moo->connect(...)`
- OPTIONS: `$moo->options(...)`
- TRACE: `$moo->trace(...)`
- PATCH: `$moo->patch(...)`

You can match multiple methods using `$moo->route(...)`.

Routes are matched from the first to the last. If no route matches the request, the error handler is called `$moo->error(...)`.
Registering the same method and URI twice replaces the earlier route.

If the path matches but the method does not, the response is `405 Method Not
Allowed` with an `Allow` header listing the registered methods.

The request path is URL-decoded before matching, so `/a%20b` matches `/a b` and
`%2F` behaves like a path separator. Route URIs are regular expressions, so
characters with a special meaning in a pattern have to be escaped. Invalid
patterns are rejected when the route is registered.

### Parameters

Route can be parametrized with regular expressions.

~~~php
$moo->post('/orders/(\d+)/items/(\d+)', function ($orderId, $itemId) use ($moo) {
    return $moo->orderService->findOrderItem($orderId, $itemId);
});
~~~

Capture groups are passed to the handler as arguments, in order.

### Before & After hooks

There are two handlers pre and post request, the before and the after.

~~~php
$moo->before = function () {
    echo "Hey I'm the before hook";
};
$moo->after = function () {
    echo "Hey I'm the after hook";
};
$moo();
~~~

### Error Handling

By default exceptions and errors are handled by the error handler, which also handles the case when no route matches the request.

~~~php
$moo->error = function (\Throwable $exc) use ($moo) {
    $moo->response->setCode(StatusCode::normalize($exc->getCode()));
    $moo->response->body = $exc->getMessage();
};
~~~

The handler receives a `\Throwable`. `StatusCode::normalize()` maps anything
outside the HTTP error range (400 to 599), including non-integer exception codes,
to `500`. The `after` hook is not executed when the error handler runs.

The default handler clears response headers, but keeps `Allow` for `405`
responses. It also puts the exception message in the response body; override the
handler in production when messages can leak paths, SQL or credentials.

### Request

The `Moo\Request` class consists of:
- method
- uri
- baseUri
- headers
- query
- post
- files
- server
- body

Request object `$moo->request` is created before `before` with the values taken from PHP global variables, `$_SERVER`, `$_GET`, `$_POST`, `$_FILES`.

`headers` contains normalized HTTP headers: `HTTP_X_TEST` becomes `X-Test`,
`CONTENT_TYPE` becomes `Content-Type`, and `REDIRECT_HTTP_AUTHORIZATION` becomes
`Authorization`. The raw server array is available as `server`. Use
`$moo->request->header('Content-Type')` for a case-insensitive lookup.

*Request body is empty by default!*
You can override that in the `before` hook:

~~~php
$moo->before = function () use ($moo) {
    $moo->request->body = file_get_contents('php://input');
};
~~~

`baseUri` is the directory the front controller script runs in, extracted from
the script name. It is `/` when it cannot be determined. A request for the base
URI itself, for example `/app` when the script lives in `/app/`, is normalized to
`/`.

### Response

The `Moo\Response` class consists of:
- body
- headers
- code
- message

Response object `$moo->response` is created before `before` and can be modified during the request lifecycle. Response body is not serialized, it is assumed you serialize it before `flush` in `after`.

Use `$response->setCode(404)` to keep `code` and `message` in sync. Assigning
`$response->code` directly does not update `message`, so prefer the setter. The
default flush sends only `code` via `http_response_code()`; `message` is useful
for custom flush handlers and `toArray()`.

### State

You can use Moo to keep your application state.

~~~php
$moo->something = 123;
$moo->myState = [
    'config' => [
        'host' => 'localhost',
        'port' => 1234
    ]
];
~~~

### Plugins

You can extend Moo with custom functions.

~~~php
$moo->foobar = function () {
    return 123;
};
$moo->foobar();
~~~

Calling an undefined plugin throws `BadMethodCallException`.

### Collection

`Moo\Collection` is a small key/value container used for headers, query, post,
files, server variables and routes.

~~~php
$headers = new Moo\Collection(['Accept' => 'text/html']);

$headers->set('X-Test', '1');
$headers->add('appended');
$headers->get('missing', 'default');
$headers->has('Accept');
$headers->remove('X-Test');
$headers->count();

foreach ($headers as $name => $value) {
    // ...
}

$headers->Accept;   // magic read
$headers['Accept']; // ArrayAccess
~~~

`get()` returns the given default when the key does not exist, `has()` reports
presence (a `null` value is still present). `set(null, $value)` appends, matching
`$collection[] = $value`. Use `set()` for nested values; assigning through
`$collection['a']['b']` does not modify the stored array.

### Model

`Moo\Model` is the base for `Request`, `Response` and `Route`. It can be
populated from an array and converted back.

~~~php
$request = new Moo\Request([
    'method' => 'POST',
    'headers' => ['Content-Type' => 'application/json'],
    'query' => ['page' => 1],
]);

$request->headers->get('Content-Type');
$request->toArray();
~~~

`populate()` only assigns declared properties and fills nested `Collection`s
from arrays. Unknown keys are ignored.

### Flush & Output Buffering

By default all output is buffered and then flushed at the end of `$moo()` execution.
You can override that behaviour by setting custom flush handler.

~~~php
$moo->flush = function () use ($moo) {
    if (!headers_sent()) {
        http_response_code($moo->response->code);
        foreach ($moo->response->headers as $name => $value) {
            header($name.': '.$value, true);
        }
    }
    if ($moo->request->method !== 'HEAD' && !in_array($moo->response->code, [204, 304], true)) {
        echo $moo->response->body;
    }
};
~~~

The default flush uses `http_response_code()` and skips the body for `HEAD`,
`204` and `304` responses. It also checks `headers_sent()` before sending
headers.

The flush handler only runs for the outermost dispatch, so nested apps and Moo
embedded in another output buffered application still return the flush result.
Setting `$moo->flush = null` disables flushing, which is handy in tests.

## PHP Compatibility

Moo targets PHP 8.0 and newer (below 9.0) and is written to stay clean on 8.1 through 8.5:

- dynamic state and plugins are enabled with `#[\AllowDynamicProperties]`
- nullable parameters are explicit (`?array $context = null`)
- collections are iterated with generators, not `ArrayIterator`
- no `null` array offsets, no `ReflectionProperty::setAccessible()`, no `Serializable`
- no `strict_types`, so typed handler parameters keep the usual PHP coercion

## Security

- The default error handler puts the exception message in the response body.
  Override `error` in production when messages can contain paths, SQL or
  credentials.
- Templates are native PHP; output is not escaped automatically. Escape
  untrusted values with `htmlspecialchars()`.
- `examples/var-dump` is for development only.
- Route patterns are developer-controlled; do not build them from user input.

## Testing

Moo is unit tested. Common targets:

~~~sh
make test       # test suite, no coverage driver required
make coverage   # tests plus HTML coverage in test/results (requires Xdebug)
make lint       # php -l over src, test and examples
make phpstan    # PHPStan level 6 over src
make check      # test + lint + phpstan + composer validate
~~~

CI runs the suite on PHP 8.1 through 8.5, a coverage job on 8.1 and a PHP 8.0
runtime/lint/smoke job.

## Contributing

The repository lives at https://github.com/hellworx/moo.
Contributions are welcome, please submit a pull request.

## License

MIT
