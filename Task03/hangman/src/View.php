<?php
namespace Tanner\Hangman\View;

class View {
    public static function renderStartScreen(): void {
        echo "=== Игра 'Виселица' ===\n";
        echo "Меню:\n";
        echo "1) Начать новую игру\n";
        echo "2) Правила игры\n";
        echo "3) Выход\n\n";
    }

    public static function renderRules(): void {
        echo "Правила игры:\n";
        echo "- Компьютер загадывает слово из 6 букв.\n";
        echo "- Вы пытаетесь угадать буквы.\n";
        echo "- Если буква есть в слове — она открывается.\n";
        echo "- Если буквы нет — дорисовывается часть висельника.\n";
        echo "- Победа: угадать слово до завершения рисунка.\n\n";
    }
}
