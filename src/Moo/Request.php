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
        parent::__construct($data);
    }
}
