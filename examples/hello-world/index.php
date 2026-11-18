<?php

require __DIR__ . '/../../vendor/autoload.php';

$moo = new Moo\Moo();

$moo->get('/', function () {
    echo "Hello world!";
});

$moo();
