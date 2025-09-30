#!/usr/bin/env php
<?php
$Path1 = __DIR__ . '/../../../autoload.php';
$Path2 = __DIR__ . '/../vendor/autoload.php';

if (file_exists($Path1)) {
    include_once $Path1;
} else {
    include_once $Path2;
}

use Tanner\Hangman\Controller;

Controller\run($argv);