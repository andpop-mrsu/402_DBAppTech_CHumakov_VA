<?php
namespace Tanner\Hangman\Controller;

use Tanner\Hangman\View\View;

function startGame() {
    View::renderStartScreen();
}