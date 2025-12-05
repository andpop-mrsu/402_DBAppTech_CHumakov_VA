<?php

declare(strict_types=1);

/**
 * Front Controller для REST API игры "Виселица".
 *
 * Маршруты (по заданию):
 *   GET  /games          — список всех игр
 *   GET  /games/{id}     — данные об одной игре и её ходах
 *   POST /games          — создать новую игру
 *   POST /step/{id}      — добавить ход в игру с id
 *
 * Дополнительно (для удобства): 
 *   POST /games/{id}/result — обновить результат игры (win/lose)
 *
 * Также:
 *   GET /                — отдаём index.html
 *
 * Запуск встроенного PHP-сервера (из каталога Task08):
 *
 *   php -S localhost:3000 -t public public/index.php
 *
 * Тогда:
 *   http://localhost:3000/          — фронтенд
 *   http://localhost:3000/games     — REST API (список игр)
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Роутер для встроенного сервера PHP ---
// Если запрошен реально существующий файл (CSS/JS/картинки) — отдаём его как static.
$staticPath = __DIR__ . $uri;
if ($uri !== '/' && is_file($staticPath)) {
    return false; // отдать файл как есть
}

// --- Роутинг по REST-маршрутам ---
if ($uri === '/' && $method === 'GET') {
    // Корень сайта — SPA, отдаем index.html
    $indexFile = __DIR__ . '/index.html';
    if (!is_file($indexFile)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "index.html не найден.";
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    readfile($indexFile);
    exit;
}

try {
    routeRequest($method, $uri);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['error' => 'Internal server error'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

/**
 * Маршрутизация REST API.
 */
function routeRequest(string $method, string $uri): void
{
    // /games
    if ($uri === '/games') {
        if ($method === 'GET') {
            handleGetGames();
            return;
        }

        if ($method === 'POST') {
            handlePostGame();
            return;
        }
    }

    // /games/{id}
    if (preg_match('#^/games/(\d+)$#', $uri, $m)) {
        $id = (int) $m[1];

        if ($method === 'GET') {
            handleGetGame($id);
            return;
        }

        // Доп. маршрут для установки результата игры
        if ($method === 'POST') {
            handlePostGameResult($id);
            return;
        }
    }

    // /step/{id}
    if (preg_match('#^/step/(\d+)$#', $uri, $m)) {
        $id = (int) $m[1];

        if ($method === 'POST') {
            handlePostStep($id);
            return;
        }
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['error' => 'Not found'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

/**
 * Подключение к SQLite + инициализация схемы при первом обращении.
 */
function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbDir = __DIR__ . '/../db';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }

    $dsn = 'sqlite:' . $dbDir . '/hangman.sqlite';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    initSchema($pdo);

    return $pdo;
}

/**
 * Создание таблиц, если их ещё нет.
 */
function initSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS games (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            player_name TEXT NOT NULL,
            secret_word TEXT NOT NULL,
            played_at   TEXT NOT NULL,
            result      TEXT NOT NULL DEFAULT "in-progress"
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS attempts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id    INTEGER NOT NULL,
            attempt_no INTEGER NOT NULL,
            letter     TEXT NOT NULL,
            success    INTEGER NOT NULL,
            FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
        )'
    );
}

/**
 * Прочитать JSON-тело запроса.
 *
 * @return array<string, mixed>
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON in request body');
    }

    return $data;
}

/**
 * Отправить JSON-ответ.
 *
 * @param mixed $data
 */
function sendJson($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * GET /games — список всех игр.
 */
function handleGetGames(): void
{
    $pdo = getPdo();

    $sql = 'SELECT id, player_name, secret_word, played_at, result
            FROM games
            ORDER BY id DESC';
    $stmt = $pdo->query($sql);

    $games = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $games[] = [
            'id'         => (int) $row['id'],
            'playerName' => (string) $row['player_name'],
            'secretWord' => (string) $row['secret_word'],
            'playedAt'   => (string) $row['played_at'],
            'result'     => (string) $row['result'],
        ];
    }

    sendJson($games);
}

/**
 * GET /games/{id} — данные об игре и её ходах.
 */
function handleGetGame(int $id): void
{
    $pdo = getPdo();

    $stmt = $pdo->prepare(
        'SELECT id, player_name, secret_word, played_at, result
         FROM games
         WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $gameRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gameRow) {
        sendJson(['error' => 'Game not found'], 404);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT attempt_no, letter, success
         FROM attempts
         WHERE game_id = :id
         ORDER BY attempt_no ASC'
    );
    $stmt->execute([':id' => $id]);

    $attempts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $attempts[] = [
            'attemptNo' => (int) $row['attempt_no'],
            'letter'    => (string) $row['letter'],
            'success'   => (bool) $row['success'],
        ];
    }

    $game = [
        'id'         => (int) $gameRow['id'],
        'playerName' => (string) $gameRow['player_name'],
        'secretWord' => (string) $gameRow['secret_word'],
        'playedAt'   => (string) $gameRow['played_at'],
        'result'     => (string) $gameRow['result'],
    ];

    sendJson(['game' => $game, 'attempts' => $attempts]);
}

/**
 * POST /games — создать новую игру.
 *
 * Ожидаемый JSON:
 * {
 *   "playerName": "Имя",
 *   "secretWord": "planet",
 *   "playedAt": "2025-12-05T10:00:00Z"
 * }
 */
function handlePostGame(): void
{
    $data = getJsonInput();

    $playerName = trim((string) ($data['playerName'] ?? ''));
    $secretWord = trim((string) ($data['secretWord'] ?? ''));
    $playedAt   = trim((string) ($data['playedAt'] ?? ''));

    if ($playerName === '' || $secretWord === '' || $playedAt === '') {
        sendJson(['error' => 'Missing required fields'], 400);
        return;
    }

    $pdo = getPdo();

    $stmt = $pdo->prepare(
        'INSERT INTO games (player_name, secret_word, played_at, result)
         VALUES (:name, :word, :played_at, :result)'
    );
    $stmt->execute([
        ':name'       => $playerName,
        ':word'       => mb_strtolower($secretWord),
        ':played_at'  => $playedAt,
        ':result'     => 'in-progress',
    ]);

    $id = (int) $pdo->lastInsertId();

    sendJson(['id' => $id], 201);
}

/**
 * POST /step/{id} — добавить ход в игру.
 *
 * Ожидаемый JSON:
 * {
 *   "attemptNo": 1,
 *   "letter": "a",
 *   "success": true
 * }
 */
function handlePostStep(int $gameId): void
{
    $pdo = getPdo();

    // Проверяем, что игра существует
    $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
    $stmt->execute([':id' => $gameId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        sendJson(['error' => 'Game not found'], 404);
        return;
    }

    $data = getJsonInput();

    $attemptNo = (int) ($data['attemptNo'] ?? 0);
    $letter    = strtolower(trim((string) ($data['letter'] ?? '')));
    $success   = !empty($data['success']);

    if ($attemptNo <= 0 || !preg_match('/^[a-z]$/', $letter)) {
        sendJson(['error' => 'Invalid attempt data'], 400);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO attempts (game_id, attempt_no, letter, success)
         VALUES (:game_id, :attempt_no, :letter, :success)'
    );
    $stmt->execute([
        ':game_id'   => $gameId,
        ':attempt_no'=> $attemptNo,
        ':letter'    => $letter,
        ':success'   => $success ? 1 : 0,
    ]);

    sendJson(['status' => 'ok'], 201);
}

/**
 * POST /games/{id}/result — обновить результат игры (win/lose).
 *
 * Ожидаемый JSON:
 * {
 *   "result": "win" | "lose"
 * }
 */
function handlePostGameResult(int $gameId): void
{
    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
    $stmt->execute([':id' => $gameId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        sendJson(['error' => 'Game not found'], 404);
        return;
    }

    $data = getJsonInput();
    $result = strtolower(trim((string) ($data['result'] ?? '')));

    if (!in_array($result, ['win', 'lose'], true)) {
        sendJson(['error' => 'Invalid result value'], 400);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE games
         SET result = :result
         WHERE id = :id'
    );
    $stmt->execute([
        ':result' => $result,
        ':id'     => $gameId,
    ]);

    sendJson(['status' => 'ok']);
}
