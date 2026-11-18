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

    public function populate(array $data): static
    {
        $syncMessage = !array_key_exists('message', $data);
        parent::populate($data);
        if ($syncMessage) {
            $this->message = StatusCode::message($this->code);
        }
        return $this;
    }

    public function setCode(int $code): static
    {
        $this->code = $code;
        $this->message = StatusCode::message($code);
        return $this;
    }
}
