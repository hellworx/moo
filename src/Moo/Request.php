<?php

namespace Moo;

class Request extends Model
{
    public string $method;
    public string $uri;
    public string $baseUri;
    public Collection $headers;
    public Collection $query;
    public Collection $post;
    public Collection $files;
    public Collection $server;
    public mixed $body = null;

    public function __construct(?array $data = null)
    {
        $this->method = 'GET';
        $this->uri = '/';
        $this->baseUri = '/';
        $this->headers = new Collection();
        $this->query = new Collection();
        $this->post = new Collection();
        $this->files = new Collection();
        $this->server = new Collection();
        parent::__construct($data);
    }

    /**
     * Case insensitive header lookup (HTTP header names are case insensitive).
     */
    public function header(string $name): mixed
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }
}
