<?php

declare(strict_types=1);

namespace Tanner\Hangman;

use DateTimeImmutable;
use RedBeanPHP\R;

/**
 * Класс Game инкапсулирует всю работу с базой данных.
 * В ЛР-5 вместо PDO и "сырых" SQL-запросов используется ORM RedBeanPHP.
 */
final class Game
{
    public function __construct()
    {
        Database::init();
        $this->seedDefaultWordsIfEmpty();
    }

    /**
     * Если таблица со словами пуста — заполняем дефолтным набором
     * 6-буквенных английских слов.
     */
    private function seedDefaultWordsIfEmpty(): void
    {
        // RedBean создаст таблицу "word" сам при первом сохранении
        if (R::count('word') > 0) {
            return;
        }

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

        foreach ($defaultWords as $word) {
            $normalized = mb_strtolower(trim($word));
            if ($normalized === '') {
                continue;
            }

            $bean = R::dispense('word');
            $bean->word = $normalized;
            R::store($bean);
        }
    }

    /**
     * Добавить слово вручную (если потребуется расширить список).
     */
    public function addWord(string $word): void
    {
        $normalized = mb_strtolower(trim($word));
        if ($normalized === '') {
            return;
        }

        $bean = R::dispense('word');
        $bean->word = $normalized;
        R::store($bean);
    }

    /**
     * Получить случайное слово из списка.
     */
    public function getRandomWord(): string
    {
        $words = R::findAll('word');

        if ($words === []) {
            throw new \RuntimeException('В базе нет слов. Добавьте слова перед началом игры.');
        }

        $keys = \array_keys($words);
        $randomKey = $keys[\array_rand($keys)];
        $bean = $words[$randomKey];

        return (string) $bean->word;
    }

    /**
     * Создать запись игры и вернуть её ID.
     */
    public function createGame(string $playerName, string $secretWord): int
    {
        $game = R::dispense('game');
        $game->played_at   = (new DateTimeImmutable())->format('c');
        $game->player_name = $playerName;
        $game->secret_word = $secretWord;
        // по умолчанию считаем, что игрок проиграл, затем обновим
        $game->result      = 'lose';

        return (int) R::store($game);
    }

    /**
     * Зафиксировать результат игры (win/lose).
     */
    public function finishGame(int $gameId, bool $win): void
    {
        /** @var \RedBeanPHP\OODBBean $game */
        $game = R::load('game', $gameId);

        if ((int) $game->id === 0) {
            // игры с таким ID нет
            return;
        }

        $game->result = $win ? 'win' : 'lose';
        R::store($game);
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
        $attempt = R::dispense('attempt');
        $attempt->game_id    = $gameId;
        $attempt->attempt_no = $attemptNo;
        $attempt->letter     = mb_strtolower($letter);
        $attempt->success    = $success ? 1 : 0;

        R::store($attempt);
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
        // Условие ' ORDER BY id DESC ' — это часть DSL RedBean для сортировки
        $beans = R::findAll('game', ' ORDER BY id DESC ');

        $result = [];

        foreach ($beans as $bean) {
            $result[] = [
                'id'          => (int) $bean->id,
                'played_at'   => (string) $bean->played_at,
                'player_name' => (string) $bean->player_name,
                'secret_word' => (string) $bean->secret_word,
                'result'      => (string) $bean->result,
            ];
        }

        return $result;
    }

    /**
     * Данные по одной игре + список её попыток.
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
        /** @var \RedBeanPHP\OODBBean $game */
        $game = R::load('game', $gameId);

        if ((int) $game->id === 0) {
            return null;
        }

        $attemptBeans = R::find(
            'attempt',
            ' game_id = ? ORDER BY attempt_no ASC ',
            [$gameId]
        );

        $attempts = [];

        foreach ($attemptBeans as $bean) {
            $attempts[] = [
                'attempt_no' => (int) $bean->attempt_no,
                'letter'     => (string) $bean->letter,
                'success'    => (int) $bean->success,
            ];
        }

        return [
            'id'          => (int) $game->id,
            'played_at'   => (string) $game->played_at,
            'player_name' => (string) $game->player_name,
            'secret_word' => (string) $game->secret_word,
            'result'      => (string) $game->result,
            'attempts'    => $attempts,
        ];
    }
}
