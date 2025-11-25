<?php

declare(strict_types=1);

namespace Tanner\Hangman;

use PDO;

final class Database
{
    /**
     * Файл базы данных SQLite.
     * Используем расположение из архива: bin/hangman.db
     */
    private const DB_FILE = __DIR__ . '/../bin/hangman.db';

    public static function getConnection(): PDO
    {
        $dbDir = \dirname(self::DB_FILE);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0775, true);
        }

        $dsn = 'sqlite:' . self::DB_FILE;

        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Включаем поддержку внешних ключей
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
