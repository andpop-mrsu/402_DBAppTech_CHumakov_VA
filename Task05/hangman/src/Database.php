<?php

declare(strict_types=1);

namespace Tanner\Hangman;

use RedBeanPHP\R;

final class Database
{
    /**
     * Путь к файлу базы данных SQLite.
     */
    private const DB_FILE = __DIR__ . '/../bin/hangman.db';

    /**
     * Инициализация подключения RedBeanPHP.
     * Вызывается один раз за запуск.
     */
    public static function init(): void
    {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        $dbDir = \dirname(self::DB_FILE);
        if (!\is_dir($dbDir)) {
            \mkdir($dbDir, 0775, true);
        }

        // Подключение к SQLite через RedBean
        R::setup('sqlite:' . self::DB_FILE);

        // В "fluid"-режиме RedBean сам создаёт и изменяет таблицы
        R::freeze(false);

        $initialized = true;
    }
}
