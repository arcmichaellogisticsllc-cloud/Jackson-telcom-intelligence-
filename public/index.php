<?php

session_start();
require __DIR__ . '/../vendor_autoload.php';

$router = require __DIR__ . '/../routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

