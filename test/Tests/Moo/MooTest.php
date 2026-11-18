<?php

namespace Tests\Moo;

use PHPUnit\Framework\TestCase;
use Moo\Moo;
use Moo\Response;
use Moo\Request;
use Moo\Router;
use Moo\StatusCode;
use Moo\Template;

function array_exclude(array $excluded, array $source)
{
    $result = [];
    foreach ($source as $element) {
        if (!in_array($element, $excluded)) {
            array_push($result, $element);
        }
    }
    return $result;
}

class ClassyMooMock extends Moo
{
    public int $state = 0;

    public function __construct()
    {
        parent::__construct();
        $this->get('/', [$this, 'index']);
        $this->post('/test/(\d+)', [$this, 'test']);
    }

    public function before()
    {
        $this->state++;
    }

    public function after()
    {
        $this->state++;
    }

    public function flush()
    {
        // Keep it empty.
    }

    public function error(\Throwable $exc)
    {
        $this->state = -1;
        $this->response = new Response([
            'code' => 404,
            'message' => 'Not Found',
            'body' => 'error'
        ]);
    }

    public function index()
    {
        return 'hello world';
    }

    public function test($x)
    {
        $this->response->setCode(202);
        return $x;
    }
}

class MooTest extends TestCase
{
    const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH', 'GET'];

    public function testBeforeAfter()
    {
        $test = 0;
        $moo = new Moo();
        $moo->flush = null;
        $moo->before =function () use (&$test) {
            $test++;
        };
        $moo->after = function () use (&$test) {
            $test++;
        };
        $moo->route('/', function () { return 'xxx'; });
        $moo();
        $this->assertEquals(2, $test);
    }

    public function testCustomError()
    {
        $test = 0;
        $moo = new Moo();
        $moo->before = function () use(&$test) {
            $test++;
            throw new \RuntimeException("test");
        };
        $moo->error = function(\Exception $exc) use(&$test) {
            $test++;
        };
        $moo();
        $this->assertEquals(2, $test);
    }
    
    public function testFlush()
    {
        $moo = new Moo();
        $moo->error = function () {};
        $moo->flush = function () use ($moo) { return strtoupper($moo->response->body); };
        $moo->route('/', function () { return 'hello'; });
        $content = $moo();
        $this->assertEquals("HELLO", $content);
    }

    public function testPluginAndState()
    {
        $moo = new Moo();
        $moo->state = 0;
        $moo->plugin = function ($x) use ($moo) {
            $moo->state = $x;
            return $x;
        };
        $y = $moo->plugin(123);
        $this->assertEquals(123, $y);
        $this->assertEquals(123, $moo->state);
    }

    public function testPluginException()
    {
        $this->expectException(\BadMethodCallException::class);
        $moo = new Moo();
        $moo->nonExistentPlugin();
    }

    public function testPluginTypeException()
    {
        $this->expectException(\BadMethodCallException::class);
        $moo = new Moo();
        $moo->plugin = 123;
        $moo->plugin();
    }

    public function testIndex()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->route('/', function () {
            return 'this is index';
        });
        $moo();
        $this->assertEquals(200, $moo->response->code);
        $this->assertEquals('OK', $moo->response->message);
        $this->assertEquals('this is index', $moo->response->body);
    }

    public function testNotFound()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo(new Request(['uri' => '/dfhsdhfg']));
        $this->assertEquals(404, $moo->response->code);
        $this->assertEquals('Not Found', $moo->response->message);
        $this->assertEquals('Not Found', $moo->response->body);
    }

    public function testRoute()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->route('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, self::METHODS, ['/', '//', '///'], 'ok');
        $this->assertRouteNotFound($moo, self::METHODS, ['/foobar']);
    }

    public function testRouteOverride()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->route('/', function () {
            return 1;
        });
        $moo->route('/', function () {
            return 2;
        });
        $moo->route('/', function () {
            return 3;
        });
        $moo();
        $this->assertEquals(3, $moo->response->body);
        $this->assertEquals(1, $moo->router->routes->count());
    }

    public function testRouteParameters()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->route('/', function () {
            return 'index';
        });
        $moo->route('/test', function () {
            return 'test';
        });
        $moo->route('/test/(\d+)', function ($x) {
            return $x;
        });
        $moo->route('/test/(\d+)/test', function ($x) {
            return 'test 2';
        });
        $moo->route('/test/([a-z]+)/test', function ($x) {
            return 'test 3 '.$x;
        });
        $moo();
        $this->assertEquals('index', $moo->response->body);
        $moo(new Request(['uri' => '/test']));
        $this->assertEquals('test', $moo->response->body);
        $moo(new Request(['uri' => '/test/123']));
        $this->assertEquals('123', $moo->response->body);
        $moo(new Request(['uri' => '/test/123/test']));
        $this->assertEquals('test 2', $moo->response->body);
        $moo(new Request(['uri' => '/test/ok/test']));
        $this->assertEquals('test 3 ok', $moo->response->body);
        $moo(new Request(['uri' => '/test/ABC/test']));
        $this->assertEquals(404, $moo->response->code);
        $this->assertEquals('Not Found', $moo->response->message);
        $this->assertEquals('Not Found', $moo->response->body);
    }

    public function testMultiRoute()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->router->register(['GET', 'POST'], ['/foo/(\d+)', '/bar/(\d+)'], function ($x) {
            return $x;
        });
        $this->assertRouteFound($moo, ['GET', 'POST'], ['/foo/123', '/bar/123'], '123');
        $this->assertRouteNotFound($moo, array_exclude(['GET', 'POST'], self::METHODS), ['/foo', '/bar']);
        $this->assertRouteNotFound($moo, self::METHODS, ['/', '/foo/abc', '/bar/xyz']);        
    }

    public function testGet()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['GET'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['GET'], self::METHODS), ['/'], 'GET');
    }

    public function testHead()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->head('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['HEAD'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['HEAD'], self::METHODS), ['/'], 'HEAD');
    }

    public function testPost()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->post('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['POST'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['POST'], self::METHODS), ['/'], 'POST');
    }

    public function testPut()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->put('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['PUT'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['PUT'], self::METHODS), ['/'], 'PUT');
    }

    public function testDelete()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->delete('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['DELETE'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['DELETE'], self::METHODS), ['/'], 'DELETE');
    }

    public function testConnect()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->connect('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['CONNECT'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['CONNECT'], self::METHODS), ['/'], 'CONNECT');
    }

    public function testOptions()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->options('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['OPTIONS'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['OPTIONS'], self::METHODS), ['/'], 'OPTIONS');
    }

    public function testTrace()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->trace('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['TRACE'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['TRACE'], self::METHODS), ['/'], 'TRACE');
    }

    public function testPatch()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->patch('/', function () {
            return 'ok';
        });
        $moo();
        $this->assertRouteFound($moo, ['PATCH'], ['/'], 'ok');
        $this->assertMethodNotAllowed($moo, array_exclude(['PATCH'], self::METHODS), ['/'], 'PATCH');
    }

    public function testCallableReturnValueOverrideBody()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/test1', function () use ($moo) {
            $moo->response->body = 123;
        });
        $moo->get('/test2', function () use ($moo) {
            $moo->response->body = 456;
            return 'xyz';
        });

        $moo(new Request(['uri' => '/test1']));
        $this->assertEquals(123, $moo->response->body);

        $moo(new Request(['uri' => '/test2']));
        $this->assertEquals('xyz', $moo->response->body);
    }

    public function testClassyMoo()
    {
        $app = new ClassyMooMock();

        $app(new Request());
        $this->assertEquals(200, $app->response->code);
        $this->assertEquals('hello world', $app->response->body);
        $this->assertEquals(2, $app->state);

        $app(new Request(['method' => 'POST', 'uri' => '/test/123']));
        $this->assertEquals(202, $app->response->code);
        $this->assertEquals('123', $app->response->body);
        $this->assertEquals(4, $app->state);

        $app(new Request(['method' => 'GET', 'uri' => '/test/123']));
        $this->assertEquals(404, $app->response->code);
        $this->assertEquals('error', $app->response->body);
        $this->assertEquals(-1, $app->state);
    }

    public function testNestedMoo()
    {
        $mooA = new Moo();
        $mooA->get('/a/test', function () {
            return 'test a';
        });

        $mooB = new Moo();
        $mooB->get('/b/test', function () {
            return 'test b';
        });

        $moo = new Moo();
        $moo->flush = null;
        $moo->route('/a/.*', $mooA);
        $moo->route('/b/.*', $mooB);

        $moo(new Request());
        $this->assertEquals(404, $moo->response->code);

        $moo(new Request(['uri' => '/a/']));
        $this->assertEquals(404, $moo->response->code);

        $moo(new Request(['uri' => '/a/xxx']));
        $this->assertEquals(404, $moo->response->code);

        $moo(new Request(['uri' => '/a/test']));
        $this->assertEquals(200, $moo->response->code);
        $this->assertEquals('test a', $moo->response->body);

        $moo(new Request(['uri' => '/b/']));
        $this->assertEquals(404, $moo->response->code);

        $moo(new Request(['uri' => '/b/xxx']));
        $this->assertEquals(404, $moo->response->code);

        $moo(new Request(['uri' => '/b/test']));
        $this->assertEquals(200, $moo->response->code);
        $this->assertEquals('test b', $moo->response->body);
    }

    public function testTemplateErrors()
    {
        $moo = new Moo();
        $moo->flush = null;

        $moo->template = function (string $script) use ($moo) {
            return (new Template(__DIR__ . '/templates'))->render($script);
        };

        $moo->get('/', function () use ($moo) {
            return $moo->template('dummy.phtml');
        });

        $moo->get('/test3', function () use ($moo) {
            return $moo->template('test3.phtml');
        });

        $moo();
        $this->assertEquals(500, $moo->response->code);

        $moo(new Request(['uri' => '/test3']));
        $this->assertEquals(500, $moo->response->code);
    }

    public function testTemplateLoadWithPartial()
    {
        $moo = new Moo();
        $moo->flush = null;

        $moo->template = function (string $script) use ($moo) {
            return (new Template(__DIR__ . '/templates'))->render($script);
        };

        $moo->get('/', function () use ($moo) {
            return $moo->template('test.phtml');
        });

        $moo();
        $this->assertEquals(200, $moo->response->code);
        $this->assertStringContainsString('test partial', $moo->response->body);
    }

    public function testTemplateContextAndPlugin()
    {
        $moo = new Moo();
        $moo->flush = null;

        $moo->template = function (string $script, array $context = []) use ($moo) {
            $template = new Template(__DIR__ . '/templates', [
                'global' => 'test'
            ]);
            $template->plugin = function ($x) { return $x; };
            return $template->render($script, $context);
        };

        $moo->get('/', function () use ($moo) {
            return $moo->template('test2.phtml', [
                'local' => 'ok',
                'var' => 123
            ]);
        });

        $moo();
        $this->assertEquals(200, $moo->response->code);
        $this->assertStringContainsString('test ok 123', $moo->response->body);
    }

    public function testTemplateIsConfinedToLookupDir()
    {
        $this->expectException(\RuntimeException::class);
        (new Template(__DIR__ . '/templates'))->render('../MooTest.php');
    }

    public function testTemplateContextDoesNotOverrideInternals()
    {
        $template = new Template(__DIR__ . '/templates');
        $body = $template->render('context.phtml', [
            'value' => 'ok',
            '__file' => '/etc/passwd',
            '__vars' => ['value' => 'overridden'],
        ]);
        $this->assertEquals('ok', $body);
    }

    public function testTemplateMissingDir()
    {
        $this->expectException(\RuntimeException::class);
        (new Template(__DIR__ . '/does-not-exist'))->render('index.phtml');
    }

    public function testErrorHandlerReceivesThrowable()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/', function () {
            throw new \TypeError('boom');
        });
        $moo(new Request(['uri' => '/']));
        $this->assertEquals(500, $moo->response->code);
        $this->assertEquals('boom', $moo->response->body);
    }

    public function testErrorCodeIsClampedToHttpStatus()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/notfound', function () {
            throw new \RuntimeException('nope', 404);
        });
        $moo->get('/nothttp', function () {
            throw new \RuntimeException('nope', 1000);
        });
        $moo->get('/nocode', function () {
            throw new \RuntimeException('nope');
        });

        $moo(new Request(['uri' => '/notfound']));
        $this->assertEquals(404, $moo->response->code);
        $this->assertEquals('Not Found', $moo->response->message);

        $moo(new Request(['uri' => '/nothttp']));
        $this->assertEquals(500, $moo->response->code);
        $this->assertEquals('Internal Server Error', $moo->response->message);

        $moo(new Request(['uri' => '/nocode']));
        $this->assertEquals(500, $moo->response->code);
    }

    public function testInvalidRoutePattern()
    {
        $this->expectException(\InvalidArgumentException::class);
        $moo = new Moo();
        $moo->get('/foo(#', function () {
            return 'x';
        });
    }

    public function testNestedCollectionPopulation()
    {
        $request = new Request([
            'method' => 'POST',
            'headers' => ['X-Test' => 'yes'],
            'query' => ['q' => 1],
            'unknown' => 'ignored',
        ]);
        $this->assertEquals('yes', $request->headers->get('X-Test'));
        $this->assertEquals(1, $request->query->q);
        $this->assertEquals(['X-Test' => 'yes'], $request->toArray()['headers']);
        $this->assertArrayNotHasKey('unknown', $request->toArray());
    }

    public function testFlushIgnoresExternalOutputBuffers()
    {
        $moo = new Moo();
        $moo->flush = function () use ($moo) {
            return 'flushed:' . $moo->response->body;
        };
        $moo->get('/', function () {
            return 'body';
        });

        ob_start();
        ob_start();
        try {
            $result = $moo(new Request(['uri' => '/']));
        } finally {
            ob_end_clean();
            ob_end_clean();
        }
        $this->assertEquals('flushed:body', $result);
    }

    public function testNestedMooFlushesOnlyOnce()
    {
        $innerFlushes = 0;
        $inner = new Moo();
        $inner->flush = function () use (&$innerFlushes) {
            $innerFlushes++;
            return 'inner-flush';
        };
        $inner->get('/inner', function () {
            return 'inner';
        });

        $outer = new Moo();
        $outer->flush = function () use ($outer) {
            return $outer->response->body;
        };
        $outer->route('/inner', $inner);

        $this->assertEquals('inner', $outer(new Request(['uri' => '/inner'])));
        $this->assertEquals(0, $innerFlushes);
    }

    public function testBaseUriIsExtractedFromScriptName()
    {
        $server = $_SERVER;
        try {
            $_SERVER['SCRIPT_NAME'] = '/app/index.php';
            $_SERVER['PHP_SELF'] = '/app/index.php';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/app/books/1';

            $request = Router::requestFactory();
            $this->assertEquals('/app/', $request->baseUri);
            $this->assertEquals('/books/1', $request->uri);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testBaseUriIgnoresBuiltinServerRouterScript()
    {
        $server = $_SERVER;
        try {
            $_SERVER['SCRIPT_NAME'] = '/books/1';
            $_SERVER['PHP_SELF'] = '/books/1';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/books/1';

            $request = Router::requestFactory();
            $this->assertEquals('/', $request->baseUri);
            $this->assertEquals('/books/1', $request->uri);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testBodyTypesArePreservedWithoutOutput()
    {
        $values = [
            ['a' => 1],
            new \stdClass(),
            false,
            0,
            1.5,
        ];

        foreach ($values as $value) {
            $moo = new Moo();
            $moo->flush = null;
            $moo->get('/', function () use ($value) {
                return $value;
            });

            $moo(new Request(['uri' => '/']));
            $this->assertSame($value, $moo->response->body);
        }
    }

    public function testBufferedOutputIsAppendedToStringBody()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/', function () {
            echo 'echoed';
            return 'returned';
        });

        $moo(new Request(['uri' => '/']));
        $this->assertSame('returnedechoed', $moo->response->body);
    }

    public function testBufferedOutputCannotBeAppendedToNonStringableBody()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/', function () {
            echo 'echoed';
            return ['a' => 1];
        });

        try {
            $moo(new Request(['uri' => '/']));
            $this->fail('Expected an UnexpectedValueException');
        } catch (\UnexpectedValueException $exc) {
            $this->assertStringContainsString('non-string response body', $exc->getMessage());
        }

        // A failed dispatch must not poison the shared depth for later requests.
        $next = new Moo();
        $next->flush = function () {
            return 'flushed';
        };
        $next->get('/', function () {
            return 'ok';
        });

        $this->assertSame('flushed', $next(new Request(['uri' => '/'])));
    }

    public function testResponseCodeSyncsMessage()
    {
        $this->assertSame('Not Found', (new Response(['code' => 404]))->message);

        $response = new Response();
        $response->populate(['code' => 500]);
        $this->assertSame('Internal Server Error', $response->message);

        $response->setCode(201);
        $this->assertSame(201, $response->code);
        $this->assertSame('Created', $response->message);

        $custom = new Response(['code' => 404, 'message' => 'Missing']);
        $this->assertSame('Missing', $custom->message);
    }

    public function testRoutesWithAndWithoutTrailingSlashCoexist()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/foo', function () {
            return 'no-slash';
        });
        $moo->get('/foo/', function () {
            return 'with-slash';
        });

        $this->assertEquals(2, $moo->router->routes->count());

        $moo(new Request(['uri' => '/foo']));
        $this->assertSame('no-slash', $moo->response->body);

        $moo(new Request(['uri' => '/foo/']));
        $this->assertSame('with-slash', $moo->response->body);
    }

    public function testMethodNotAllowedIncludesAllowHeader()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/only', function () {
            return 'get';
        });
        $moo->put('/only', function () {
            return 'put';
        });

        $moo(new Request(['method' => 'POST', 'uri' => '/only']));

        $this->assertEquals(405, $moo->response->code);
        $this->assertSame('Method Not Allowed', $moo->response->message);
        $this->assertSame('Method Not Allowed', $moo->response->body);
        $this->assertSame('GET, PUT', $moo->response->headers->get('Allow'));
    }

    public function testUriIsUrlDecoded()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->get('/a b', function () {
            return 'space';
        });

        $moo(new Request(['uri' => '/a%20b']));
        $this->assertSame('space', $moo->response->body);
    }

    public function testRequestHeadersAreNormalized()
    {
        $server = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['HTTP_X_TEST'] = 'yes';
            $_SERVER['CONTENT_TYPE'] = 'text/plain';
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer token';

            $request = Router::requestFactory();

            $this->assertSame('yes', $request->headers->get('X-Test'));
            $this->assertSame('yes', $request->header('x-test'));
            $this->assertSame('text/plain', $request->headers->get('Content-Type'));
            $this->assertSame('Bearer token', $request->headers->get('Authorization'));
            $this->assertSame('yes', $request->server->get('HTTP_X_TEST'));
            $this->assertNull($request->headers->get('HTTP_X_TEST'));
        } finally {
            $_SERVER = $server;
        }
    }

    public function testBaseUriWithoutTrailingSlashMapsToRoot()
    {
        $server = $_SERVER;
        try {
            $_SERVER['SCRIPT_NAME'] = '/app/index.php';
            $_SERVER['PHP_SELF'] = '/app/index.php';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/app?x=1';

            $request = Router::requestFactory();
            $this->assertSame('/app/', $request->baseUri);
            $this->assertSame('/?x=1', $request->uri);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testBaseUriHandlesRelativeScriptNames()
    {
        $server = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';

            $_SERVER['SCRIPT_NAME'] = 'index.php';
            $this->assertSame('/', Router::requestFactory()->baseUri);

            $_SERVER['SCRIPT_NAME'] = 'app/index.php';
            $this->assertSame('/app/', Router::requestFactory()->baseUri);
        } finally {
            $_SERVER = $server;
        }
    }

    public function testNonCallableHookIsReported()
    {
        $moo = new Moo();
        $moo->flush = null;
        $moo->before = 'not-a-function';
        $moo->get('/', function () {
            return 'ok';
        });

        $moo(new Request(['uri' => '/']));
        $this->assertEquals(500, $moo->response->code);
        $this->assertStringContainsString('Hook before is not callable', $moo->response->body);
    }

    public function testNonCallableFlushThrows()
    {
        $moo = new Moo();
        $moo->flush = 'not-a-function';
        $moo->get('/', function () {
            return 'ok';
        });

        $this->expectException(\InvalidArgumentException::class);
        $moo(new Request(['uri' => '/']));
    }

    public function testTemplateDoesNotFollowSymlinksOutsideLookupDir()
    {
        $target = tempnam(sys_get_temp_dir(), 'moo');
        file_put_contents($target, 'outside');
        $link = __DIR__ . '/templates/link.phtml';
        @unlink($link);
        if (!@symlink($target, $link)) {
            @unlink($target);
            $this->markTestSkipped('Symlinks are not available');
        }

        try {
            $this->expectException(\RuntimeException::class);
            (new Template(__DIR__ . '/templates'))->render('link.phtml');
        } finally {
            @unlink($link);
            @unlink($target);
        }
    }

    public function testStatusCodeNormalize()
    {
        $this->assertSame(404, StatusCode::normalize(404));
        $this->assertSame(500, StatusCode::normalize(StatusCode::HTTP_OK));
        $this->assertSame(500, StatusCode::normalize('42S02'));
        $this->assertSame(500, StatusCode::normalize(null));
    }

    public function testDefaultFlushRejectsNonStringableBody()
    {
        $moo = new Moo();
        $moo->request = new Request(['method' => 'GET']);
        $moo->response = new Response(['body' => ['a' => 1]]);

        $this->expectException(\UnexpectedValueException::class);
        call_user_func($moo->flush);
    }

    public function testDefaultFlushSuppressesHeadAndEmptyBodies()
    {
        $moo = new Moo();

        $moo->request = new Request(['method' => 'HEAD']);
        $moo->response = new Response(['body' => 'hidden']);
        ob_start();
        call_user_func($moo->flush);
        $this->assertSame('', ob_get_clean());

        $moo->request = new Request(['method' => 'GET']);
        $moo->response = (new Response(['body' => 'hidden']))->setCode(204);
        ob_start();
        call_user_func($moo->flush);
        $this->assertSame('', ob_get_clean());

        $moo->response = new Response(['body' => 'shown']);
        ob_start();
        call_user_func($moo->flush);
        $this->assertSame('shown', ob_get_clean());
    }

    /**
     * Keep this test last because it overrides values of global variables.
     */
    public function testRequestFactory()
    {
        global $_SERVER;
        global $_GET;
        global $_POST;
        global $_FILES;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test?xxx=123';
        $_GET['xxx'] = 123;
        $_POST['yyy'] = 456;
        $_FILES['zzz'] = ['name' => 'foo.bar'];

        $moo = new Moo();
        $moo->flush = null;
        $moo->post('/test', function () {
            return 'test';
        });

        $request = Router::requestFactory();
        $this->assertEquals('POST', $request->method);
        $this->assertEquals('/test?xxx=123', $request->uri);
        $this->assertEquals(1, $request->query->count());
        $this->assertEquals(123, $request->query->xxx);
        $this->assertEquals(1, $request->post->count());
        $this->assertEquals(456, $request->post->yyy);
        $this->assertEquals(1, $request->files->count());
        $this->assertEquals('foo.bar', $request->files->zzz['name']);

        $moo($request);
        $this->assertEquals(200, $moo->response->code);
        $this->assertEquals('OK', $moo->response->message);
        $this->assertEquals('test', $moo->response->body);
    }

    protected function assertRouteFound(Moo $moo, array $methods, array $uris, string $expected)
    {
        foreach ($methods as $method) {
            foreach ($uris as $uri) {
                $moo(new Request(['method' => $method, 'uri' => $uri]));
                $this->assertEquals($expected, $moo->response->body);
            }
        }
    }

    protected function assertRouteNotFound(Moo $moo, array $methods, array $uris)
    {
        foreach ($methods as $method) {
            foreach ($uris as $uri) {
                $moo(new Request(['method' => $method, 'uri' => $uri]));
                $this->assertEquals(404, $moo->response->code);
                $this->assertEquals('Not Found', $moo->response->message);
                $this->assertEquals('Not Found', $moo->response->body);
            }
        }
    }

    protected function assertMethodNotAllowed(Moo $moo, array $methods, array $uris, string $allowed)
    {
        foreach ($methods as $method) {
            foreach ($uris as $uri) {
                $moo(new Request(['method' => $method, 'uri' => $uri]));
                $this->assertEquals(405, $moo->response->code);
                $this->assertSame('Method Not Allowed', $moo->response->message);
                $this->assertSame('Method Not Allowed', $moo->response->body);
                $this->assertSame($allowed, $moo->response->headers->get('Allow'));
            }
        }
    }
}
