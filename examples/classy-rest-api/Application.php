<?php

require __DIR__ . '/BookService.php';

use Moo\Moo;
use Moo\StatusCode;

class Application extends Moo
{
    protected BookService $bookService;

    public function __construct()
    {
        parent::__construct();

        $this->bookService = new BookService();

        $this->get(     '/',            [$this, 'index']);
        $this->get(     '/books',       [$this, 'getBooks']);
        $this->post(    '/books',       [$this, 'addBook']);
        $this->get(     '/books/(\d+)', [$this, 'getBook']);
        $this->put(     '/books/(\d+)', [$this, 'updateBook']);
        $this->delete(  '/books/(\d+)', [$this, 'removeBook']);
    }

    public function before()
    {
        $this->request->body = file_get_contents('php://input');
    }

    public function after()
    {
        $this->response->headers->set('Content-Type', 'application/json');
        if ($this->response->code !== StatusCode::HTTP_NO_CONTENT) {
            $this->response->body = json_encode($this->response->body);
        }
    }

    public function error(\Throwable $exc)
    {
        $this->response->setCode(StatusCode::normalize($exc->getCode()));
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->body = json_encode(['error' => $exc->getMessage()]);
    }

    public function index()
    {
        return ['time' => date('Y-m-d H:i:s')];
    }

    public function getBooks()
    {
        return $this->bookService->getBooks();
    }

    public function addBook()
    {
        $this->response->setCode(StatusCode::HTTP_CREATED);
        return $this->bookService->addBook($this->request->body);
    }

    public function getBook($id)
    {
        return $this->bookService->getBook($id);
    }

    public function updateBook($id)
    {
        return $this->bookService->updateBook($id, $this->request->body);
    }

    public function removeBook($id)
    {
        $this->response->setCode(StatusCode::HTTP_NO_CONTENT);
        return $this->bookService->removeBook($id);
    }
}
