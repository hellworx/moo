<?php

namespace Tests\Moo;

use PHPUnit\Framework\TestCase;
use Moo\Collection;
use Moo\Request;

class ModelTest extends TestCase
{
    public function testPopulateIgnoresUnknownKeys()
    {
        $request = new Request(['method' => 'POST', 'uri' => '/x', 'unknown' => 'ignored']);
        $this->assertEquals('POST', $request->method);
        $this->assertEquals('/x', $request->uri);
        $this->assertArrayNotHasKey('unknown', $request->toArray());
    }

    public function testPopulateNestedCollections()
    {
        $request = new Request([
            'headers' => ['X-Test' => 'yes'],
            'query' => ['q' => 1],
        ]);
        $this->assertInstanceOf(Collection::class, $request->headers);
        $this->assertEquals('yes', $request->headers->get('X-Test'));
        $this->assertEquals(1, $request->query->q);
    }

    public function testToArrayIsRecursive()
    {
        $request = new Request(['query' => ['q' => 1]]);
        $array = $request->toArray();
        $this->assertSame(['q' => 1], $array['query']);
        $this->assertSame('GET', $array['method']);
    }
}
