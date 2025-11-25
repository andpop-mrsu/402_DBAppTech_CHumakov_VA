<?php

declare(strict_types=1);

namespace Tanner\Hangman;

use PDO;

final class Game
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->initSchema();
        $this->seedDefaultWordsIfEmpty();
    }

    /**
     * Создание таблиц words, games, attempts (если ещё нет).
     */
    private function initSchema(): void
    {
        // таблица слов
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS words (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                word TEXT NOT NULL UNIQUE
            )"
        );

        // таблица игр
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS games (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                played_at    TEXT    NOT NULL,
                player_name  TEXT    NOT NULL,
                secret_word  TEXT    NOT NULL,
                result       TEXT    NOT NULL
            )"
        );

        // таблица попыток
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS attempts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id     INTEGER NOT NULL,
                attempt_no  INTEGER NOT NULL,
                letter      TEXT    NOT NULL,
                success     INTEGER NOT NULL,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            )"
        );
    }

    /**
     * Если таблица words пустая — заполняем дефолтными английскими словами.
     */
    private function seedDefaultWordsIfEmpty(): void
    {
        $stmt  = $this->pdo->query('SELECT COUNT(*) FROM words');
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            return;
        }

        // 6-буквенные английские слова
        $defaultWords = [
            'planet',
            'rocket',
            'socket',
            'object',
            'window',
            'screen',
            'python',
            'letter',
            'summer',
            'winter',
            'spring',
            'forest',
            'castle',
            'bridge',
            'driver',
            'school',
            'mother',
            'father',
            'little',
            'golden',
        ];

        $stmtInsert = $this->pdo->prepare('INSERT OR IGNORE INTO words (word) VALUES (:word)');

        foreach ($defaultWords as $word) {
            $normalized = mb_strtolower(trim($word));
            if ($normalized === '') {
                continue;
            }
            $stmtInsert->execute(['word' => $normalized]);
        }
    }

    /**
     * Добавить слово вручную (опционально).
     */
    public function addWord(string $word): void
    {
        $normalized = mb_strtolower(trim($word));
        if ($normalized === '') {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO words (word) VALUES (:word)');
        $stmt->execute(['word' => $normalized]);
    }

    /**
     * Получить случайное слово из списка.
     */
    public function getRandomWord(): string
    {
        $stmt = $this->pdo->query(
            'SELECT word FROM words ORDER BY RANDOM() LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || !isset($row['word'])) {
            throw new \RuntimeException('В таблице words нет слов. Добавьте слова в базу.');
        }

        return (string) $row['word'];
    }

    /**
     * Создать запись игры и вернуть её ID.
     */
    public function createGame(string $playerName, string $secretWord): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO games (played_at, player_name, secret_word, result)
             VALUES (:played_at, :player_name, :secret_word, :result)'
        );

        $stmt->execute([
            'played_at'   => (new \DateTimeImmutable())->format('c'),
            'player_name' => $playerName,
            'secret_word' => $secretWord,
            // По умолчанию: проигрыш, обновим при завершении
            'result'      => 'lose',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Зафиксировать результат игры.
     */
    public function finishGame(int $gameId, bool $win): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE games SET result = :result WHERE id = :id'
        );

        $stmt->execute([
            'result' => $win ? 'win' : 'lose',
            'id'     => $gameId,
        ]);
    }

    /**
     * Добавить попытку игрока.
     */
    public function addAttempt(
        int $gameId,
        int $attemptNo,
        string $letter,
        bool $success
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attempts (game_id, attempt_no, letter, success)
             VALUES (:game_id, :attempt_no, :letter, :success)'
        );

        $stmt->execute([
            'game_id'    => $gameId,
            'attempt_no' => $attemptNo,
            'letter'     => mb_strtolower($letter),
            'success'    => $success ? 1 : 0,
        ]);
    }

    /**
     * Список всех игр.
     *
     * @return array<int, array{
     *   id:int,
     *   played_at:string,
     *   player_name:string,
     *   secret_word:string,
     *   result:string
     * }>
     */
    public function getAllGames(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, played_at, player_name, secret_word, result
             FROM games
             ORDER BY id DESC'
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Данные по одной игре + список попыток.
     *
     * @return array{
     *   id:int,
     *   played_at:string,
     *   player_name:string,
     *   secret_word:string,
     *   result:string,
     *   attempts:array<int,array{attempt_no:int,letter:string,success:int}>
     * }|null
     */
    public function getGameWithAttempts(int $gameId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, played_at, player_name, secret_word, result
             FROM games
             WHERE id = :id'
        );
        $stmt->execute(['id' => $gameId]);

        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($game === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT attempt_no, letter, success
             FROM attempts
             WHERE game_id = :game_id
             ORDER BY attempt_no ASC'
        );
        $stmt->execute(['game_id' => $gameId]);

        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!\is_array($attempts)) {
            $attempts = [];
        }

        $game['attempts'] = $attempts;

        /** @var array{
         *   id:int,
         *   played_at:string,
         *   player_name:string,
         *   secret_word:string,
         *   result:string,
         *   attempts:array<int,array{attempt_no:int,letter:string,success:int}>
         * } $game
         */
        return $game;
    }
}
