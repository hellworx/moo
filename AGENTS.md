# AGENTS.md

## General rules
- Don't overengineer
- KISS - Keep It Simple Stupid
- YAGNI - You Ain't Gonna Need It
- Update AGENTS.md with relevant development information
- Update README.md and other docs when necessary

## Project

Moo is a small, flexible PHP front controller: regex routing, request/response
models, before/after/error hooks, native PHP templates. It is not a toy and not a
full-stack framework - it is what you use when plain PHP scripts are not enough.

Ownership: the package is `hellworx/moo`, owned by Hellworx, repository
<https://github.com/hellworx/moo>. Keep the `composer.json` package name and the
README install command in sync, and do not reintroduce references to the
previous owner or the removed dependencies.

Hard constraints:
- **No runtime dependencies.** Moo must stay self contained; `composer.json`
  `require` contains nothing but `php`. Everything else lives in `src/Moo`.
- PHP 8.0+ runtime, below 9.0, written to stay deprecation clean on 8.1-8.5.
- No PSR-7/PSR-15, no DI container, no config system. Design stays closure based.
- Do not add `declare(strict_types=1)`: handlers are called from library code, so
  strict types would break the usual scalar coercion for user handlers.

## Layout

| Path | Purpose |
|---|---|
| `src/Moo/Moo.php` | Entry point: HTTP method helpers, hooks, lifecycle in `_dispatch()` |
| `src/Moo/Router.php` | Route registry, dispatch, `requestFactory()` from superglobals |
| `src/Moo/Route.php` | Route model (method, uri, callback) |
| `src/Moo/Request.php` | Request model (method, uri, baseUri, headers, query, post, files, server, body) |
| `src/Moo/Response.php` | Response model (body, headers, code, message) and `setCode()` |
| `src/Moo/Extendable.php` | `__call` that turns public callable properties into methods (plugins) |
| `src/Moo/Collection.php` | Self contained key/value container (ArrayAccess, Countable, IteratorAggregate) |
| `src/Moo/Model.php` | Base model with `populate()` / `toArray()` |
| `src/Moo/Template.php` | Native PHP template renderer |
| `src/Moo/StatusCode.php` | HTTP status constants, messages and `normalize()` |
| `test/Tests/Moo/` | PHPUnit suite (MooTest, CollectionTest, ModelTest) |
| `examples/` | Runnable apps, one directory per feature |

## Commands

~~~sh
make test                      # PHPUnit with coverage, fails on warnings/deprecations/notices
make lint                      # php -l over src, test and examples
php vendor/bin/phpunit --filter testRoute test/Tests/Moo/MooTest.php
php -S 0.0.0.0:8080 examples/hello-world/index.php
~~~

- PHPUnit is a dev dependency (`^10.5 || ^11.5`); use `vendor/bin/phpunit`.
- Coverage needs Xdebug (`XDEBUG_MODE=coverage` is set in the Makefile).
- Dev tooling needs PHP 8.1, runtime supports 8.0.
- Only PHP 8.1 is available locally, so that is the only version actually
  executed. Compatibility with 8.2-8.5 is maintained by the rules below, not by
  a test run - keep it that way unless a runner is available.

## Lifecycle contract

`$moo($request, $response)` runs `_dispatch()`:

1. request/response are injected, or built by `Router::requestFactory()` / `new Response()`
2. `ob_start()` and the shared dispatch depth is incremented
3. the `before` hook, then router dispatch; a non-null handler result becomes the body
4. the `after` hook
5. on `\Throwable`: the `error` hook if configured, otherwise the exception is rethrown
6. `finally`: buffered output is captured, the depth is decremented, and non-empty
   output is appended to the body
7. `flush` runs only when the dispatch depth is back to 0 and a flush hook exists

Consequences to preserve:
- Only `\Throwable` is caught. If there is no error handler the exception is
  rethrown after the buffer is cleaned up and the depth is restored.
- `after` does **not** run when the error handler runs.
- When nothing was echoed, the handler return value is stored as-is (arrays,
  `false`, `0` and objects keep their type). Echoed output is appended **after**
  `after`, so it bypasses serialization; in that case the body must be a scalar,
  `null` or `Stringable`, otherwise `UnexpectedValueException` is thrown.
- The depth is decremented **before** the body is appended. Never move work that
  can throw between `self::$depth++` and the decrement; a leaked depth disables
  flushing for every later request in the process.
- The body is never serialized by Moo; serialize in `after` (see `examples/rest-api`).
- Flush suppression uses a process-wide `Moo::$depth`, not `ob_get_level()`, so
  nesting and embedding in a foreign output buffer both work.
- Hooks can be closures in properties or methods on a subclass; a real method
  wins over the property closure. A `null` hook is skipped; a non-null
  non-callable hook throws `InvalidArgumentException` (before/after failures are
  then handled by the error handler).
- The default error handler normalizes the HTTP code and preserves the `Allow`
  header for `405` responses.
- The default flush uses `http_response_code()`, checks `headers_sent()` and
  skips the body for `HEAD`, `204` and `304`.
- Nested apps share the outer `Request`/`Response`. Each level runs its own
  `after`, so serialize on only one level. Calling a nested app without passing
  the response discards its output; nest through `route()`.

## Routing contract

- Route URIs are raw PCRE patterns, wrapped as `#^{uri}$#`: escape regex
  metacharacters and avoid `#` in patterns.
- `route()` registers method `*`; `get/post/...` register a single method.
- Routes are stored in a `Collection` keyed by `"METHOD uri"` using the raw
  pattern, so exact re-registration replaces the route while `/foo` and `/foo/`
  can coexist.
- Matching parses the URI, collapses repeated slashes, URL-decodes the path and
  passes capture groups to the handler as ordered arguments. `%2F` therefore
  behaves like `/`.
- If the path matches but no method does, dispatch sets `Allow` and throws
  `RuntimeException('Method Not Allowed', 405)`.
- A `Moo` instance is a valid callback (nesting): it is invoked with
  `(Request, Response)` via `call_user_func`.
- Invalid patterns throw `InvalidArgumentException` in `register()`; no match
  throws `RuntimeException('Not Found', 404)`.
- `Router::requestFactory()` derives `baseUri` from the script name only up to
  the first `.php`, so the builtin server's router script mode does not eat the
  first URI segment. A request for the base URI without its trailing slash maps
  to `/` (the query string is kept).

## Conventions and gotchas

- `before`, `after`, `error` and `flush` are declared `mixed` properties;
  plugins and application state are dynamic properties, allowed by
  `#[\AllowDynamicProperties]` on `Extendable` (inherited by subclasses).
  Do not introduce new declared-property reads before initialization - use
  `isset()` when that is a possibility.
- Use `Response::setCode()` when changing the status: it updates `message` too.
  `StatusCode::normalize()` turns any non-4xx/5xx or non-integer exception code
  into 500.
- `Request::$headers` contains normalized HTTP headers (`X-Test`,
  `Content-Type`, `Authorization`); `Request::$server` keeps the raw `$_SERVER`
  array. Prefer `Request::header()` for case-insensitive lookup.
- `Collection::get()` returns the default for missing keys; `has()` uses
  `array_key_exists()` so a `null` value still counts as present. `set(null, ...)`
  appends, like `add()`. Use `set()` for nested values - PHP cannot write through
  `offsetGet()`.
- `Model::populate()` only assigns declared properties and fills existing
  `Collection` properties from arrays; unknown keys are ignored.
- Templates: `Template::render()` confines scripts to the lookup directory
  (`realpath` check, symlinks outside are rejected) and renders with `EXTR_SKIP`
  so context cannot override internal variables. Partials are included relative
  to the including template.
- Exceptions thrown by handlers do not need an HTTP code; anything outside
  400-599 becomes 500. `StatusCode::message()` always returns a string.
- Implicitly nullable parameters are not allowed (`?type $x = null`) - PHP 8.4
  deprecates the implicit form.
- Collections iterate with generators; `ArrayIterator` on objects and
  `Serializable` are deprecated (8.5) and must not come back.

## Docs duty

- `README.md` is the user facing specification. Update it when the lifecycle,
  routing, public API or supported PHP versions change.
- Add a directory under `examples/` for a new feature instead of only documenting it.
- Keep this file accurate: it is the entry point for anyone (human or agent)
  touching the code.
