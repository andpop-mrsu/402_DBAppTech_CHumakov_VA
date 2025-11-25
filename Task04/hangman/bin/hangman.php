#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tanner\Hangman\Controller;
use Tanner\Hangman\Database;
use Tanner\Hangman\Game;
use Tanner\Hangman\View;

$shortOpts = 'nlr:h';
$longOpts  = [
    'new',
    'list',
    'replay:',
    'help',
];

$options = getopt($shortOpts, $longOpts);

$pdo   = Database::getConnection();
$game  = new Game($pdo);   // <-- вместо Model
$view  = new View();
$controller = new Controller($game, $view);

$scriptName = basename($_SERVER['argv'][0] ?? 'hangman.php');

$hasHelp   = isset($options['h']) || isset($options['help']);
$hasList   = isset($options['l']) || isset($options['list']);
$hasReplay = isset($options['r']) || isset($options['replay']);
$hasNew    = isset($options['n']) || isset($options['new']);

if ($hasHelp) {
    $controller->showHelp($scriptName);
    exit(0);
}

if ($hasList) {
    $controller->listGames();
    exit(0);
}

if ($hasReplay) {
    $idRaw = $options['r'] ?? $options['replay'];

    if (is_array($idRaw)) {
        $idRaw = reset($idRaw);
    }

    $id = (int) $idRaw;
    $controller->replayGame($id);
    exit(0);
}

if ($hasNew) {
    $controller->newGame();
    exit(0);
}

// Нет аргументов — интерактивное меню.
$controller->runInteractive($scriptName);
