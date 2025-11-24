<?php

declare(strict_types=1);

namespace Tanner\Hangman;

final class Controller
{
    public function __construct(
        private Game $game,
        private View $view,
    ) {
    }

    public function showHelp(string $scriptName): void
    {
        $this->view->showHelp($scriptName);
    }

    /**
     * Интерактивное меню (без аргументов).
     */
    public function runInteractive(string $scriptName): void
    {
        while (true) {
            $this->view->showMainMenu($scriptName);
            $choice = $this->view->askMenuChoice();

            switch ($choice) {
                case '1':
                    $this->newGame();
                    $this->view->waitForEnter('Нажмите Enter, чтобы вернуться в меню...');
                    break;

                case '2':
                    $this->listGames();
                    $this->view->waitForEnter('Нажмите Enter, чтобы вернуться в меню...');
                    break;

                case '3':
                    $id = $this->view->askReplayGameId();
                    if ($id > 0) {
                        $this->replayGame($id);
                    }
                    $this->view->waitForEnter('Нажмите Enter, чтобы вернуться в меню...');
                    break;

                case '0':
                    $this->view->showExit();
                    return;
            }
        }
    }

    /**
     * Режим: новая игра.
     */
    public function newGame(): void
    {
        $this->view->showWelcome();
        $playerName = $this->view->askPlayerName();

        $secretWord = $this->game->getRandomWord();
        $gameId     = $this->game->createGame($playerName, $secretWord);

        $maxErrors      = 6;
        $errors         = 0;
        $attemptNo      = 1;
        $guessedLetters = [];

        while (true) {
            $maskedWord = $this->buildMaskedWord($secretWord, $guessedLetters);

            $this->view->renderGameState(
                $maskedWord,
                $errors,
                $maxErrors,
                $guessedLetters,
                $attemptNo
            );

            $letter = $this->view->askLetter($attemptNo);
            if ($letter === '') {
                continue;
            }

            $alreadyTried = \in_array($letter, $guessedLetters, true);
            $success      = false;

            if (!$alreadyTried) {
                $guessedLetters[] = $letter;

                if (mb_strpos($secretWord, $letter) !== false) {
                    $success = true;
                } else {
                    $errors++;
                }
            }

            $this->game->addAttempt($gameId, $attemptNo, $letter, $success);
            $this->view->showAttemptResult($letter, $success, $alreadyTried);

            if ($this->isWordGuessed($secretWord, $guessedLetters) || $errors >= $maxErrors) {
                break;
            }

            $attemptNo++;
        }

        $win = $this->isWordGuessed($secretWord, $guessedLetters);
        $this->game->finishGame($gameId, $win);

        $this->view->renderFinal(
            $secretWord,
            $playerName,
            $errors,
            $maxErrors,
            $guessedLetters,
            $win
        );
    }

    /**
     * Режим: список игр.
     */
    public function listGames(): void
    {
        $games = $this->game->getAllGames();
        $this->view->renderGamesList($games);
    }

    /**
     * Режим: повтор партии.
     */
    public function replayGame(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $gameData = $this->game->getGameWithAttempts($id);
        if ($gameData === null) {
            return;
        }

        $this->view->renderReplayHeader($gameData);

        $secretWord     = (string) $gameData['secret_word'];
        $playerName     = (string) $gameData['player_name'];
        $maxErrors      = 6;
        $errors         = 0;
        $guessedLetters = [];

        $attempts = $gameData['attempts'];

        foreach ($attempts as $attempt) {
            $letter       = (string) $attempt['letter'];
            $attemptNo    = (int) $attempt['attempt_no'];
            $alreadyTried = \in_array($letter, $guessedLetters, true);
            $success      = false;

            if (!$alreadyTried) {
                $guessedLetters[] = $letter;

                if (mb_strpos($secretWord, $letter) !== false) {
                    $success = true;
                } else {
                    $errors++;
                }
            }

            $maskedWord = $this->buildMaskedWord($secretWord, $guessedLetters);

            $this->view->renderReplayStep(
                $maskedWord,
                $errors,
                $maxErrors,
                $guessedLetters,
                $attemptNo,
                $letter,
                $success
            );
        }

        $win = $this->isWordGuessed($secretWord, $guessedLetters);

        $this->view->renderFinal(
            $secretWord,
            $playerName,
            $errors,
            $maxErrors,
            $guessedLetters,
            $win
        );
    }

    /**
     * Маска слова вида "_ _ a _ _ b".
     *
     * @param string[] $guessedLetters
     */
    private function buildMaskedWord(string $secretWord, array $guessedLetters): string
    {
        $chars = preg_split('//u', $secretWord, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return '';
        }

        $result = [];

        foreach ($chars as $char) {
            $lower = mb_strtolower($char);
            if (\in_array($lower, $guessedLetters, true)) {
                $result[] = $char;
            } else {
                $result[] = '_';
            }
        }

        return implode(' ', $result);
    }

    /**
     * Проверка, что все буквы слова угаданы.
     *
     * @param string[] $guessedLetters
     */
    private function isWordGuessed(string $secretWord, array $guessedLetters): bool
    {
        $chars = preg_split('//u', $secretWord, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return false;
        }

        foreach ($chars as $char) {
            $lower = mb_strtolower($char);
            if (!\in_array($lower, $guessedLetters, true)) {
                return false;
            }
        }

        return true;
    }
}
