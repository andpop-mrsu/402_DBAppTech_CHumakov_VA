<?php

declare(strict_types=1);

namespace Tanner\Hangman;

final class View
{
    public function showWelcome(): void
    {
        $this->clearScreen();
        $this->writeln('=== Игра "Виселица" ===');
        $this->writeln('Компьютер загадывает слово из шести букв (латиница).');
        $this->writeln('Угадайте все буквы до того, как человечек будет полностью нарисован.');
        $this->writeln('');
    }

    public function showHelp(string $scriptName): void
    {
        $this->writeln('Игра "Виселица"');
        $this->writeln('');
        $this->writeln('Режимы запуска:');
        $this->writeln(sprintf('  php %s [--new|-n]       Новая игра (без меню)', $scriptName));
        $this->writeln(sprintf('  php %s --list|-l        Список всех сохранённых игр', $scriptName));
        $this->writeln(sprintf('  php %s --replay|-r ID   Пошаговый повтор игры с номером ID', $scriptName));
        $this->writeln(sprintf('  php %s --help|-h        Показать эту справку', $scriptName));
        $this->writeln('');
        $this->writeln(sprintf(
            '  php %s                  Запуск с интерактивным меню',
            $scriptName
        ));
        $this->writeln('');
    }

    /** Главное меню для интерактивного режима. */
    public function showMainMenu(string $scriptName): void
    {
        $this->clearScreen();
        $this->writeln('=== Игра "Виселица" — меню ===');
        $this->writeln(sprintf(''));
        $this->writeln('');
        $this->writeln('1. Новая игра');
        $this->writeln('2. Список сохранённых игр');
        $this->writeln('3. Повтор сохранённой игры');
        $this->writeln('0. Выход');
        $this->writeln('');
    }

    public function askMenuChoice(): string
    {
        while (true) {
            $this->write('Выберите пункт меню (0–3): ');
            $line = fgets(STDIN);

            if ($line === false) {
                return '0';
            }

            $choice = trim($line);

            if (\in_array($choice, ['0', '1', '2', '3'], true)) {
                return $choice;
            }

            $this->writeln('Неверный выбор. Введите число от 0 до 3.');
        }
    }

    public function askReplayGameId(): int
    {
        while (true) {
            $this->write('Введите ID игры для повтора (или 0 для отмены): ');
            $line = fgets(STDIN);

            if ($line === false) {
                return 0;
            }

            $line = trim($line);
            if ($line === '') {
                $this->writeln('Пустой ввод. Попробуйте ещё раз.');
                continue;
            }

            if (!ctype_digit($line)) {
                $this->writeln('Нужно ввести неотрицательное целое число.');
                continue;
            }

            return (int) $line;
        }
    }

    public function waitForEnter(string $message = 'Нажмите Enter, чтобы продолжить...'): void
    {
        $this->writeln($message);
        fgets(STDIN);
    }

    public function showExit(): void
    {
        $this->clearScreen();
        $this->writeln('Выход из игры. До свидания!');
        $this->writeln('');
    }

    public function askPlayerName(): string
    {
        while (true) {
            $this->write('Введите имя игрока: ');
            $line = fgets(STDIN);

            if ($line === false) {
                return 'Player';
            }

            $name = trim($line);
            if ($name !== '') {
                return $name;
            }

            $this->writeln('Имя не может быть пустым. Попробуйте ещё раз.');
        }
    }

    public function askLetter(int $attemptNo): string
    {
        while (true) {
            $this->write(sprintf('Попытка %d. Введите букву: ', $attemptNo));
            $line = fgets(STDIN);

            if ($line === false) {
                return '';
            }

            $input = trim($line);
            if ($input === '') {
                $this->writeln('Пустой ввод. Попробуйте ещё раз.');
                continue;
            }

            $letter = mb_strtolower(mb_substr($input, 0, 1));

            // Разрешаем только латинские буквы a–z
            if (!preg_match('/^[a-z]$/i', $letter)) {
                $this->writeln('Нужно ввести одну латинскую букву (a-z).');
                continue;
            }

            return $letter;
        }
    }

    public function showAttemptResult(string $letter, bool $success, bool $alreadyTried): void
    {
        if ($alreadyTried) {
            $this->writeln(sprintf('Буква "%s" уже была использована.', $letter));
        } elseif ($success) {
            $this->writeln(sprintf('Буква "%s" есть в слове!', $letter));
        } else {
            $this->writeln(sprintf('Буквы "%s" нет в слове.', $letter));
        }

        $this->writeln('');
    }

    /**
     * @param string[] $guessedLetters
     */
    public function renderGameState(
        string $maskedWord,
        int $errors,
        int $maxErrors,
        array $guessedLetters,
        int $attemptNo
    ): void {
        $this->clearScreen();

        $this->writeln('=== Игра "Виселица" ===');
        $this->writeln('');
        $this->writeln($this->buildGallows($errors));
        $this->writeln('');
        $this->writeln('Слово: ' . $maskedWord);
        $this->writeln(sprintf('Ошибок: %d из %d', $errors, $maxErrors));
        $this->writeln(
            'Использованные буквы: ' . (empty($guessedLetters) ? '—' : implode(', ', $guessedLetters))
        );
        $this->writeln('');
        $this->writeln(sprintf('Ход №%d', $attemptNo));
        $this->writeln('');
    }

    /**
     * @param string[] $guessedLetters
     */
    public function renderFinal(
        string $secretWord,
        string $playerName,
        int $errors,
        int $maxErrors,
        array $guessedLetters,
        bool $win
    ): void {
        $this->clearScreen();

        $this->writeln('=== Конец игры ===');
        $this->writeln('');
        $this->writeln($this->buildGallows($errors));
        $this->writeln('');
        $this->writeln('Загаданное слово: ' . $secretWord);
        $this->writeln(sprintf('Ошибок: %d из %d', $errors, $maxErrors));
        $this->writeln(
            'Использованные буквы: ' . (empty($guessedLetters) ? '—' : implode(', ', $guessedLetters))
        );
        $this->writeln('');

        if ($win) {
            $this->writeln(sprintf('Поздравляем, %s! Вы угадали слово.', $playerName));
        } else {
            $this->writeln(sprintf('Увы, %s, вы проиграли.', $playerName));
        }

        $this->writeln('');
    }

    /**
     * @param array<int, array{id:int,played_at:string,player_name:string,secret_word:string,result:string}> $games
     */
    public function renderGamesList(array $games): void
    {
        if ($games === []) {
            $this->writeln('Сохранённых игр пока нет.');
            return;
        }

        $this->writeln('Список сохранённых игр:');
        $this->writeln('');
        $this->writeln('ID | Дата игры           | Игрок       | Слово   | Результат');
        $this->writeln('---+----------------------+-------------+---------+----------');

        foreach ($games as $game) {
            $this->writeln(sprintf(
                '%3d| %-20s | %-11s | %-7s | %-8s',
                (int) $game['id'],
                (string) $game['played_at'],
                mb_substr((string) $game['player_name'], 0, 11),
                mb_substr((string) $game['secret_word'], 0, 7),
                ((string) $game['result'] === 'win') ? 'угадал' : 'не угадал'
            ));
        }

        $this->writeln('');
    }

    /**
     * @param array{
     *   id:int,
     *   played_at:string,
     *   player_name:string,
     *   secret_word:string,
     *   result:string,
     *   attempts:array<int,array{attempt_no:int,letter:string,success:int}>
     * } $game
     */
    public function renderReplayHeader(array $game): void
    {
        $this->clearScreen();
        $this->writeln('=== Повтор игры ===');
        $this->writeln('');
        $this->writeln('ID:     ' . $game['id']);
        $this->writeln('Дата:   ' . $game['played_at']);
        $this->writeln('Игрок:  ' . $game['player_name']);
        $this->writeln('Слово:  ' . $game['secret_word']);
        $this->writeln('Исход:  ' . ($game['result'] === 'win' ? 'угадал' : 'не угадал'));
        $this->writeln('');
        $this->writeln('Нажмите Enter, чтобы начать пошаговый повтор...');
        fgets(STDIN);
    }

    /**
     * @param string[] $guessedLetters
     */
    public function renderReplayStep(
        string $maskedWord,
        int $errors,
        int $maxErrors,
        array $guessedLetters,
        int $attemptNo,
        string $letter,
        bool $success
    ): void {
        $this->clearScreen();
        $this->writeln('=== Повтор игры ===');
        $this->writeln('');
        $this->writeln($this->buildGallows($errors));
        $this->writeln('');
        $this->writeln('Слово: ' . $maskedWord);
        $this->writeln(sprintf('Ошибок: %d из %d', $errors, $maxErrors));
        $this->writeln(
            'Использованные буквы: ' . (empty($guessedLetters) ? '—' : implode(', ', $guessedLetters))
        );
        $this->writeln('');
        $this->writeln(sprintf(
            'Ход №%d: буква "%s" — %s',
            $attemptNo,
            $letter,
            $success ? 'угадал' : 'не угадал'
        ));
        $this->writeln('');
        $this->writeln('Нажмите Enter, чтобы перейти к следующему ходу...');
        fgets(STDIN);
    }

    private function buildGallows(int $errors): string
    {
        $states = [
            [
                '+---+',
                '    |',
                '    |',
                '    |',
                '   ===',
            ],
            [
                '+---+',
                '0   |',
                '    |',
                '    |',
                '   ===',
            ],
            [
                '+---+',
                '0   |',
                '|   |',
                '    |',
                '   ===',
            ],
            [
                '+---+',
                '0   |',
                '/|  |',
                '    |',
                '   ===',
            ],
            [
                '+---+',
                '0   |',
                '/|\\ |',
                '    |',
                '   ===',
            ],
            [
                '+---+',
                '0   |',
                '/|\\ |',
                '/   |',
                '   ===',
            ],
            [
                '+---+',
                '0   |',
                '/|\\ |',
                '/ \\ |',
                '   ===',
            ],
        ];

        $index = max(0, min($errors, \count($states) - 1));

        return implode(PHP_EOL, $states[$index]);
    }

    private function clearScreen(): void
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            echo str_repeat(PHP_EOL, 50);
        } else {
            echo "\033[2J\033[;H";
        }
    }

    private function write(string $text): void
    {
        fwrite(STDOUT, $text);
    }

    private function writeln(string $text = ''): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }
}
