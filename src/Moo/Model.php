<?php

namespace Moo;

/**
 * Plain data model with array population and conversion back to an array.
 */
abstract class Model
{
    /**
     * @param array<mixed, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        if (isset($data)) {
            $this->populate($data);
        }
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public function populate(array $data): static
    {
        foreach ($data as $key => $value) {
            if (!property_exists($this, (string) $key)) {
                continue;
            }
            if (isset($this->{$key}) && $this->{$key} instanceof Collection && is_array($value)) {
                $this->{$key}->populate($value);
            } else {
                $this->{$key} = $value;
            }
        }
        return $this;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof Model || $value instanceof Collection) {
                $value = $value->toArray();
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
