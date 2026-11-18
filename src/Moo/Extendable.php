<?php

namespace Moo;

/**
 * Turns public callable properties into methods and allows storing arbitrary
 * state and plugins on the object.
 */
#[\AllowDynamicProperties]
abstract class Extendable
{
    /**
     * @param array<int, mixed> $args
     */
    public function __call(string $name, array $args): mixed
    {
        foreach (get_object_vars($this) as $key => $callback) {
            if ($key === $name) {
                if (is_callable($callback)) {
                    return call_user_func_array($callback, $args);
                }
                throw new \BadMethodCallException(static::class . "::$name is not callable");
            }
        }
        throw new \BadMethodCallException(static::class . "::$name is not callable");
    }
}
