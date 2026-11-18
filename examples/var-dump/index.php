<?php

require __DIR__ . '/../../vendor/autoload.php';

$moo = new Moo\Moo();

// Development only: this dumps the full server environment.
$moo->dumpServerVariables = function () {
    var_dump($_SERVER);
    var_dump($_GET);
    var_dump($_POST);
    var_dump($_FILES);
};

$moo->route('/(.*)', function () use ($moo) {
    $moo->dumpServerVariables();
});

$moo();
