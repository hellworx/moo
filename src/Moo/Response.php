<?php

namespace Moo;

class Response extends Model
{
    public mixed $body = null;
    public Collection $headers;
    public int $code;
    public string $message;

    public function __construct(?array $data = null)
    {
        $this->body = null;
        $this->headers = new Collection();
        $this->code = StatusCode::HTTP_OK;
        $this->message = StatusCode::message($this->code);
        parent::__construct($data);
    }
}
