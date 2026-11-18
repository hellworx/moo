<?php

namespace Tests\Moo;

use PHPUnit\Framework\TestCase;
use Moo\Collection;

class CollectionTest extends TestCase
{
    public function testPopulateAndAccess()
    {
        $collection = new Collection(['a' => 1, 'b' => 2]);
        $this->assertCount(2, $collection);
        $this->assertEquals(1, $collection->get('a'));
        $this->assertTrue($collection->has('b'));
        $this->assertFalse($collection->has('c'));
        $this->assertEquals(1, $collection->a);
        $this->assertTrue(isset($collection->b));
    }

    public function testMissingKeyReturnsDefault()
    {
        $collection = new Collection();
        $this->assertNull($collection->get('missing'));
        $this->assertEquals('default', $collection->get('missing', 'default'));
        $this->assertNull($collection->missing);
    }

    public function testNullValueIsTreatedAsPresent()
    {
        $collection = new Collection(['x' => null]);
        $this->assertTrue($collection->has('x'));
        $this->assertTrue(isset($collection['x']));
        $this->assertArrayHasKey('x', $collection->items());
        $this->assertNull($collection->get('x', 'default'));
    }

    public function testSetAddRemoveClear()
    {
        $collection = new Collection();
        $collection->set('a', 1);
        $collection->add(2);
        $collection['b'] = 3;
        $collection[] = 4;

        $this->assertEquals(2, $collection[0]);
        $this->assertEquals(4, $collection[1]);
        $this->assertEquals(3, $collection['b']);
        $this->assertCount(4, $collection);

        $collection->remove('a');
        unset($collection['b']);
        $this->assertCount(2, $collection);

        $collection->clear();
        $this->assertCount(0, $collection);
        $this->assertEquals([], $collection->items());
    }

    public function testSetWithNullKeyAppends()
    {
        $collection = new Collection(['a' => 1]);
        $collection->set(null, 2);

        $this->assertSame(2, $collection->get(0));
        $this->assertCount(2, $collection);
    }

    public function testMagicAccess()
    {
        $collection = new Collection();
        $collection->a = 1;
        $this->assertTrue(isset($collection->a));
        $this->assertEquals(1, $collection->a);
        unset($collection->a);
        $this->assertFalse(isset($collection->a));
    }

    public function testIterationPreservesKeys()
    {
        $seen = [];
        foreach (new Collection(['a' => 1, 'b' => 2]) as $key => $value) {
            $seen[$key] = $value;
        }
        $this->assertEquals(['a' => 1, 'b' => 2], $seen);
    }

    public function testToArrayWithNestedCollections()
    {
        $collection = new Collection(['nested' => new Collection(['x' => 1])]);
        $this->assertEquals(['nested' => ['x' => 1]], $collection->toArray());
    }
}
