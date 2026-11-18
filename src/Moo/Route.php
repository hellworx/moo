<?php

namespace Moo;

class Route extends Model
{
    public string $method = '';
    public string $uri = '';
    public mixed $callback = null;
}
