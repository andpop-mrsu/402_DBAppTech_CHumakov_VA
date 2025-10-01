<?php
namespace Tanner\Hangman\Controller;

use Tanner\Hangman\View\View;
use Tanner\Hangman\Game;

function run(array $argv): void {
    $command = $argv[1] ?? '--new';

    switch ($command) {
        case '--new':
        case '-n':
            startGame();
            break;

        case '--list':
        case '-l':
            echo "Список игр пока не сохраняется (нет БД)\n";
            break;

        case '--replay':
        case '-r':
            $id = $argv[2] ?? null;
            if ($id === null) {
                echo "Ошибка: укажите ID игры для повтора.\n";
            } else {
                echo "Повтор игры #$id пока не реализован (нет БД)\n";
            }
            break;

        case '--help':
        case '-h':
            echo "Доступные режимы:\n";
            echo "  --new, -n      Начать новую игру (по умолчанию)\n";
            echo "  --list, -l     Показать список сохранённых игр\n";
            echo "  --replay, -r   Повторить игру по ID\n";
            echo "  --help, -h     Показать справку\n";
            break;

        default:
            echo "Неизвестная команда: $command\n";
            echo "Используйте --help для справки\n";
    }
}

function startGame(): void {
    while (true) {
        View::renderStartScreen();

        echo "Выберите действие: ";
        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));

        switch ($choice) {
            case '1':
                playHangman();
                return; 
            case '2':
                View::renderRules();
                break;
            case '3':
                echo "Выход из игры.\n";
                exit;
            default:
                echo "Некорректный выбор. Попробуйте ещё раз.\n\n";
        }
    }
}

function playHangman(): void {
    $words = Game::getWords();
    $word = $words[array_rand($words)];
    $wordLetters = str_split($word);
    $guessed = array_fill(0, strlen($word), '_');

    $attempts = 0;
    $maxAttempts = 6;
    $usedLetters = [];

    echo "\nКомпьютер загадал слово из 6 букв.\nПопробуйте угадать его!\n\n";

    while ($attempts <= $maxAttempts) {
        renderHangman($attempts);
        echo "Слово: " . implode(' ', $guessed) . "\n";
        echo "Использованные буквы: " . implode(', ', $usedLetters) . "\n";

        if ($guessed === $wordLetters) {
            echo "\nПоздравляем! Вы угадали слово: $word\n";
            return;
        }

        echo "Введите букву: ";
        $handle = fopen("php://stdin", "r");
        $input = strtolower(trim(fgets($handle)));
        fclose($handle);

        if (strlen($input) !== 1 || !ctype_alpha($input)) {
            echo "Введите одну букву!\n\n";
            continue;
        }

        if (in_array($input, $usedLetters)) {
            echo "Эта буква уже была.\n\n";
            continue;
        }

        $usedLetters[] = $input;

        if (in_array($input, $wordLetters)) {
            foreach ($wordLetters as $i => $letter) {
                if ($letter === $input) {
                    $guessed[$i] = $input;
                }
            }
            echo "Буква есть в слове!\n\n";
        } else {
            $attempts++;
            echo "Буквы нет в слове. Ошибок: $attempts/$maxAttempts\n\n";
        }

        if ($attempts > $maxAttempts) {
            renderHangman($attempts);
            echo "\nИгра окончена. Загаданное слово было: $word\n";
            return;
        }
    }
}

function renderHangman(int $attempts): void {
    $stages = Game::getHangmanStages();
    echo $stages[$attempts] . "\n\n";
}
