# Task09 – SPA «Виселица» на PHP-фреймворке Slim

## Описание

Лабораторная работа №9: **реализация игры в формате SPA с помощью PHP-фреймворка Slim**.

На основе предыдущей ЛР-8 (веб-приложение «Виселица» с REST API на чистом PHP) реализовано приложение, в котором:

- интерфейс по-прежнему представляет собой **Single Page Application** (одна страница `index.html`);
- данные об играх и ходах хранятся в базе данных **SQLite**;
- обмен между браузером и сервером идёт по **REST API** в формате **JSON**;
- backend переписан на **PHP-фреймворке Slim 4** (PSR-7, PSR-15), используется единая точка входа `public/index.php`.

Игровая логика (правила «Виселицы») и интерфейс полностью наследуются из ЛР-8:

- загадывается английское слово из 6 букв;
- игрок вводит буквы по одной;
- отображаются маска слова, количество ошибок, использованные буквы и виселица;
- при 6 ошибках — поражение, при полном угадывании слова — победа;
- все партии и их ходы сохраняются в базе и доступны через историю.

---

## Структура проекта

```text
Task09/
  composer.json          # Файл зависимостей Composer
  composer.lock
  vendor/                # Устанавливается Composer (Slim, PSR-7 и т.д.)
  db/
    hangman.sqlite       # Файл базы данных SQLite (создаётся автоматически)
  public/                # Корень веб-сайта
    index.php            # Точка входа фреймворка Slim (Front Controller)
    index.html           # SPA-интерфейс игры
    styles.css           # Оформление интерфейса
    src/
      game.js            # Игровая логика (модель, без DOM и БД)
      view.js            # Работа с DOM (представление)
      controller.js      # Связь между логикой, REST API и представлением
      db.js              # Клиентский модуль для REST API на Slim + SQLite
  README.md              # Текущая документация по ЛР-9
````

Frontend (HTML, CSS, JS) перенесён из ЛР-8 **без изменений**.
Backend переписан с «самописного» фронт-контроллера на Slim, при этом формат REST API сохранён.

---

## Установка зависимостей

В каталоге `Task09` необходимо установить Slim и сопутствующие пакеты через Composer:

```bash
cd 402_DBAppTech_CHumakov_VA/Task09

composer require slim/slim slim/psr7 slim/http
```

После выполнения команды будут созданы:

* `composer.json` / `composer.lock`;
* каталог `vendor/` с кодом фреймворка Slim и зависимостями.

---

## REST API (Slim)

Backend реализует тот же набор REST-маршрутов, что и в ЛР-8:

| Метод | Путь          | Назначение                                   |
| ----- | ------------- | -------------------------------------------- |
| GET   | `/games`      | Список всех игр                              |
| GET   | `/games/{id}` | Данные об одной игре и её ходах              |
| POST  | `/games`      | Создать новую игру, вернуть её идентификатор |
| POST  | `/games/{id}` | Установить результат игры (`win` / `lose`)   |
| POST  | `/step/{id}`  | Добавить ход в игру с идентификатором `id`   |

### Структура базы данных

Схема БД такая же, как в Task08. При первом запуске `public/index.php` создаёт файл
`db/hangman.sqlite` и две таблицы:

1. Таблица `games` — список партий:

   * `id` — INTEGER PRIMARY KEY AUTOINCREMENT
   * `player_name` — имя игрока
   * `secret_word` — загаданное слово
   * `played_at` — дата и время начала игры (строка ISO)
   * `result` — исход игры:

     * `"in-progress"` — игра в процессе;
     * `"win"` — победа;
     * `"lose"` — поражение.

2. Таблица `attempts` — ходы игроков:

   * `id` — INTEGER PRIMARY KEY AUTOINCREMENT
   * `game_id` — ID игры (`games.id`)
   * `attempt_no` — номер хода (1, 2, 3, …)
   * `letter` — введённая буква
   * `success` — `1` (угадал) или `0` (ошибка)

---

## Примеры запросов и ответов

### Создание новой игры — `POST /games`

Запрос:

```json
{
  "playerName": "Alice",
  "secretWord": "planet",
  "playedAt": "2025-12-05T10:00:00Z"
}
```

Ответ (`201 Created`):

```json
{ "id": 1 }
```

---

### Добавление хода — `POST /step/{id}`

Запрос:

```json
{
  "attemptNo": 1,
  "letter": "a",
  "success": true
}
```

Ответ (`201 Created`):

```json
{ "status": "ok" }
```

---

### Обновление результата игры — `POST /games/{id}`

Запрос:

```json
{ "result": "win" }
```

или

```json
{ "result": "lose" }
```

Ответ:

```json
{ "status": "ok" }
```

---

### Список игр — `GET /games`

Ответ:

```json
[
  {
    "id": 1,
    "playerName": "Alice",
    "secretWord": "planet",
    "playedAt": "2025-12-05T10:00:00Z",
    "result": "win"
  },
  {
    "id": 2,
    "playerName": "Bob",
    "secretWord": "rocket",
    "playedAt": "2025-12-05T10:30:00Z",
    "result": "in-progress"
  }
]
```

---

### Одна игра и её ходы — `GET /games/{id}`

Ответ:

```json
{
  "game": {
    "id": 1,
    "playerName": "Alice",
    "secretWord": "planet",
    "playedAt": "2025-12-05T10:00:00Z",
    "result": "win"
  },
  "attempts": [
    { "attemptNo": 1, "letter": "a", "success": true },
    { "attemptNo": 2, "letter": "b", "success": false }
  ]
}
```

---

## Архитектура backend на Slim

Основные элементы backend-части:

* `public/index.php` — **Front Controller**, в котором:

  * подключается автозагрузка Composer;
  * создаётся экземпляр приложения Slim (`AppFactory::create()`);
  * настраивается middleware обработки ошибок;
  * регистрируются маршруты `GET /games`, `GET /games/{id}`, `POST /games`, `POST /games/{id}`, `POST /step/{id}`;
  * реализованы вспомогательные функции:

    * подключение к SQLite (`getPdo`, `initSchema`);
    * чтение JSON-тела запроса (`getJsonBody`);
    * формирование JSON-ответа (`jsonResponse`).

* все маршруты соответствуют PSR-7:

  * принимают объекты `Request` и `Response`;
  * читают тело запроса из `Request`;
  * записывают результат в `Response` и возвращают его.

Маршрут `GET /` перенаправляет на `/index.html`, где загружается SPA.

---

## Взаимодействие frontend и backend

### Модуль `public/src/db.js` (клиент REST API)

На стороне браузера модуль `db.js` предоставляет функции:

* `saveNewGame({ playerName, secretWord, playedAt })`
  → вызывает `POST /games` и возвращает ID созданной игры;

* `saveAttempt({ gameId, attemptNo, letter, success })`
  → вызывает `POST /step/{id}`;

* `finishGame(gameId, result)`
  → вызывает `POST /games/{id}` и записывает итог (`win` или `lose`);

* `getAllGames()`
  → вызывает `GET /games` и возвращает список всех партий;

* `getGameWithAttempts(gameId)`
  → вызывает `GET /games/{id}` и используется при повторе игры.

Модуль не содержит логики игры и не работает с DOM — только HTTP-запросы и обработку JSON-ответов.

### Модуль `public/src/controller.js`

Контроллер:

* создаёт экземпляры `HangmanGame` (модель) и `View` (представление);
* при `DOMContentLoaded` привязывает обработчики:

  * запуск новой игры (создание записи в БД через REST API);
  * ввод букв и сохранение ходов;
  * фиксация итогового результата;
  * загрузка списка игр и отображение в таблице;
  * повтор сохранённых партий по ID.

---

## Запуск приложения

### 1. Установка зависимостей (однократно)

```bash
cd 402_DBAppTech_CHumakov_VA/Task09
composer install        # или composer require slim/slim slim/psr7 slim/http, если ещё не выполнялось
```

Убедитесь, что в каталоге есть папка `vendor/`.

### 2. Запуск встроенного веб-сервера PHP

```bash
php -S localhost:3000 -t public public/index.php
```

* `-t public` — корень сайта (каталог `public`);
* `public/index.php` — точка входа Slim и роутер REST API.

### 3. Открытие SPA в браузере

Перейдите по адресу:

```text
http://localhost:3000/
```

Будет загружена страница с интерфейсом «Виселицы».
Все запросы `fetch` из frontend (`/games`, `/games/{id}`, `/step/{id}`) будут обрабатываться приложением Slim,
а данные сохраняться в `db/hangman.sqlite`.

---

## Использование

1. Введите имя игрока в поле «Имя игрока».
2. Нажмите **«Начать игру»**:

   * фронтенд выбирает случайное слово;
   * отправляет запрос `POST /games` на Slim-backend;
   * сервер создаёт запись игры в SQLite и возвращает её ID.
3. При вводе каждой новой буквы:

   * `controller.js` обновляет состояние игры и интерфейс;
   * отправляет `POST /step/{id}` с номером хода, буквой и результатом.
4. При завершении игры (победа/поражение):

   * фронтенд вызывает `POST /games/{id}` с `"win"` или `"lose"`;
   * сервер обновляет запись игры в таблице `games`.
5. В панели «История партий»:

   * кнопка **«Показать все сохранённые игры»** вызывает `GET /games`;
   * таблица показывает ID, имя игрока, загаданное слово, дату и результат;
   * ввод ID игры и нажатие **«Повторить игру»** вызывает `GET /games/{id}`,
     после чего ходы выбранной игры воспроизводятся на том же игровом поле.

---
Автор
ChumakovVA (SakatoGin)