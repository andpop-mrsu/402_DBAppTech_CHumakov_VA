<?php
namespace Tanner\Hangman;

class Game {
    public static function getWords(): array {
        return [
            'rabbit',
            'planet',
            'dragon',
            'flower',
            'pirate',
            'castle'
        ];
    }

    public static function getHangmanStages(): array {
        return [
            "+---+\n    |\n    |\n    |\n   ===",
            "+---+\n  0 |\n    |\n    |\n   ===",
            "+---+\n  0 |\n  | |\n    |\n   ===",
            "+---+\n  0 |\n /| |\n    |\n   ===",
            "+---+\n  0 |\n /|\\|\n    |\n   ===",
            "+---+\n  0 |\n /|\\|\n /  |\n   ===",
            "+---+\n  0 |\n /|\\|\n / \\|\n   ===",
        ];
    }
}
