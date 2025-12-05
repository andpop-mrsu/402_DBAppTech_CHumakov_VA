<?php

declare(strict_types=1);

// Если запущено через встроенный сервер PHP, отдаём статику напрямую
if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $uri;

    if ($uri !== '/' && is_file($file)) {
        return false; // пусть встроенный сервер отдаст файл сам
    }
}

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

/**
 * Вспомогательная функция: JSON-ответ.
 *
 * @param mixed $data
 */
function jsonResponse(Response $response, $data, int $statusCode = 200): Response
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus($statusCode);
}

/**
 * Подключение к SQLite + инициализация схемы.
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
 * Получить JSON-тело запроса как массив.
 *
 * @return array<string, mixed>
 */
function getJsonBody(Request $request): array
{
    $raw = (string) $request->getBody();
    if ($raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    return $data;
}

// === Маршруты Slim ===

// GET /  → переадресация на /index.html
$app->get('/', function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', '/index.html')
        ->withStatus(302);
});

// GET /games — список игр
$app->get('/games', function (Request $request, Response $response): Response {
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

    return jsonResponse($response, $games);
});

// GET /games/{id} — игра + ходы
$app->get('/games/{id}', function (Request $request, Response $response, array $args): Response {
    $id = (int) ($args['id'] ?? 0);
    if ($id <= 0) {
        return jsonResponse($response, ['error' => 'Invalid game id'], 400);
    }

    $pdo = getPdo();

    $stmt = $pdo->prepare(
        'SELECT id, player_name, secret_word, played_at, result
         FROM games
         WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $gameRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gameRow) {
        return jsonResponse($response, ['error' => 'Game not found'], 404);
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

    return jsonResponse($response, ['game' => $game, 'attempts' => $attempts]);
});

// POST /games — создать новую игру
$app->post('/games', function (Request $request, Response $response): Response {
    $data = getJsonBody($request);

    $playerName = trim((string) ($data['playerName'] ?? ''));
    $secretWord = trim((string) ($data['secretWord'] ?? ''));
    $playedAt   = trim((string) ($data['playedAt'] ?? ''));

    if ($playerName === '' || $secretWord === '' || $playedAt === '') {
        return jsonResponse($response, ['error' => 'Missing required fields'], 400);
    }

    $pdo = getPdo();

    $stmt = $pdo->prepare(
        'INSERT INTO games (player_name, secret_word, played_at, result)
         VALUES (:name, :word, :played_at, :result)'
    );
    $stmt->execute([
        ':name'      => $playerName,
        ':word'      => mb_strtolower($secretWord),
        ':played_at' => $playedAt,
        ':result'    => 'in-progress',
    ]);

    $id = (int) $pdo->lastInsertId();

    return jsonResponse($response, ['id' => $id], 201);
});

// POST /step/{id} — добавить ход
$app->post('/step/{id}', function (Request $request, Response $response, array $args): Response {
    $gameId = (int) ($args['id'] ?? 0);
    if ($gameId <= 0) {
        return jsonResponse($response, ['error' => 'Invalid game id'], 400);
    }

    $pdo = getPdo();

    // Есть ли такая игра
    $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
    $stmt->execute([':id' => $gameId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        return jsonResponse($response, ['error' => 'Game not found'], 404);
    }

    $data = getJsonBody($request);

    $attemptNo = (int) ($data['attemptNo'] ?? 0);
    $letter    = strtolower(trim((string) ($data['letter'] ?? '')));
    $success   = !empty($data['success']);

    if ($attemptNo <= 0 || !preg_match('/^[a-z]$/', $letter)) {
        return jsonResponse($response, ['error' => 'Invalid attempt data'], 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO attempts (game_id, attempt_no, letter, success)
         VALUES (:game_id, :attempt_no, :letter, :success)'
    );
    $stmt->execute([
        ':game_id'    => $gameId,
        ':attempt_no' => $attemptNo,
        ':letter'     => $letter,
        ':success'    => $success ? 1 : 0,
    ]);

    return jsonResponse($response, ['status' => 'ok'], 201);
});

// POST /games/{id} — обновить результат игры (win/lose)
$app->post('/games/{id}', function (Request $request, Response $response, array $args): Response {
    $gameId = (int) ($args['id'] ?? 0);
    if ($gameId <= 0) {
        return jsonResponse($response, ['error' => 'Invalid game id'], 400);
    }

    $pdo = getPdo();

    $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
    $stmt->execute([':id' => $gameId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        return jsonResponse($response, ['error' => 'Game not found'], 404);
    }

    $data = getJsonBody($request);
    $result = strtolower(trim((string) ($data['result'] ?? '')));

    if (!in_array($result, ['win', 'lose'], true)) {
        return jsonResponse($response, ['error' => 'Invalid result value'], 400);
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

    return jsonResponse($response, ['status' => 'ok']);
});

$app->run();
