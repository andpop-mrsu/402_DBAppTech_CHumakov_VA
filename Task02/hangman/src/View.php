<?php
namespace Tanner\Hangman\View;

class View {
    public static function renderStartScreen() {
        if (function_exists('\cli\line')) {
            \cli\line("=== Игра 'Виселица' ===");
            \cli\line("Меню:");
            \cli\line("1) Начать новую игру");
            \cli\line("2) Правила игры");
            \cli\line("3) Выход");
            \cli\line("");
        } else {
            echo "=== Игра 'Виселица' ===\n";
            echo "Меню:\n";
            echo "1) Начать новую игру\n";
            echo "2) Правила игры\n";
            echo "3) Выход\n\n";
        }
    }
}