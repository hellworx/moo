<?php

class BookService
{
    public function getBooks()
    {
        return [1, 2, 3];
    }

    public function addBook($dto)
    {
        return ['book' => json_decode($dto, true)];
    }

    public function getBook($id)
    {
        return ['id' => (int) $id, 'title' => 'Don Quixote', 'author' => 'Miguel de Cervantes'];
    }

    public function updateBook($id, $dto)
    {
        return ['id' => (int) $id, 'book' => json_decode($dto, true)];
    }

    public function removeBook($id)
    {
        return null;
    }
}
