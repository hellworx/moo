<?php

namespace Moo;

/**
 * Minimal, dependency free key/value collection.
 *
 * @implements \ArrayAccess<mixed, mixed>
 * @implements \IteratorAggregate<mixed, mixed>
 */
class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @var array<mixed, mixed> */
    protected array $items = [];

    /**
     * @param array<mixed, mixed>|null $items
     */
    public function __construct(?array $items = null)
    {
        if (isset($items)) {
            $this->populate($items);
        }
    }

    /**
     * @param iterable<mixed, mixed> $items
     */
    public function populate(iterable $items): static
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function set(mixed $key, mixed $value): static
    {
        if ($key === null) {
            return $this->add($value);
        }
        $this->items[$key] = $value;
        return $this;
    }

    public function add(mixed $value): static
    {
        $this->items[] = $value;
        return $this;
    }

    public function get(mixed $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    public function has(mixed $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function remove(mixed $key): static
    {
        unset($this->items[$key]);
        return $this;
    }

    public function clear(): static
    {
        $this->items = [];
        return $this;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return \Traversable<mixed, mixed>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->items as $key => $value) {
            if ($value instanceof Model || $value instanceof Collection) {
                $value = $value->toArray();
            }
            $result[$key] = $value;
        }
        return $result;
    }

    public function __toString(): string
    {
        return var_export($this->items, true);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->add($value);
        } else {
            $this->set($offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __unset(string $key): void
    {
        $this->remove($key);
    }
}
