// src/db.js
//
// Обертка над REST API сервера.
// Здесь НЕТ DOM и логики игры — только HTTP-запросы к PHP-бэкенду.

/**
 * Базовый URL API. Так как фронтенд и бэкенд на одном origin, достаточно пустой строки.
 * Если бы API было на другом домене, сюда бы прописали полный URL.
 */
const API_BASE = "";

/**
 * Проверка ответа сервера: если не ok — кидаем ошибку.
 */
async function handleJsonResponse(response) {
  let data = null;
  try {
    data = await response.json();
  } catch (e) {
    // пустой или некорректный JSON
  }

  if (!response.ok) {
    const message =
      (data && data.error) ||
      `HTTP error ${response.status} ${response.statusText}`;
    throw new Error(message);
  }

  return data;
}

/**
 * Сохранить новую игру на сервере.
 * Возвращает ID созданной игры.
 */
export async function saveNewGame({ playerName, secretWord, playedAt }) {
  const response = await fetch(`${API_BASE}/games`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      playerName,
      secretWord,
      playedAt,
    }),
  });

  const data = await handleJsonResponse(response);
  if (!data || typeof data.id !== "number") {
    throw new Error("Некорректный ответ сервера при создании игры.");
  }

  return data.id;
}

/**
 * Добавить ход в игру на сервере.
 */
export async function saveAttempt({ gameId, attemptNo, letter, success }) {
  const response = await fetch(`${API_BASE}/step/${encodeURIComponent(gameId)}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      attemptNo,
      letter,
      success,
    }),
  });

  await handleJsonResponse(response);
}

/**
 * Обновить результат игры на сервере (win/lose).
 */
export async function finishGame(gameId, result) {
  const response = await fetch(
    `${API_BASE}/games/${encodeURIComponent(gameId)}`, // <= БЕЗ /result
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ result }),
    }
  );

  await handleJsonResponse(response);
}


/**
 * Получить список всех игр.
 * Формат элементов массива:
 * {
 *   id: number,
 *   playerName: string,
 *   secretWord: string,
 *   playedAt: string,
 *   result: "in-progress" | "win" | "lose"
 * }
 */
export async function getAllGames() {
  const response = await fetch(`${API_BASE}/games`, {
    method: "GET",
    headers: {
      "Accept": "application/json",
    },
  });

  const data = await handleJsonResponse(response);
  if (!Array.isArray(data)) {
    throw new Error("Некорректный формат ответа сервера при получении списка игр.");
  }

  return data;
}

/**
 * Получить одну игру и её ходы.
 * Формат:
 * {
 *   game: {
 *     id: number,
 *     playerName: string,
 *     secretWord: string,
 *     playedAt: string,
 *     result: string
 *   },
 *   attempts: [
 *     { attemptNo: number, letter: string, success: boolean },
 *     ...
 *   ]
 * }
 */
export async function getGameWithAttempts(gameId) {
  const response = await fetch(
    `${API_BASE}/games/${encodeURIComponent(gameId)}`,
    {
      method: "GET",
      headers: {
        "Accept": "application/json",
      },
    }
  );

  const data = await handleJsonResponse(response);

  if (!data || typeof data !== "object") {
    throw new Error("Некорректный формат ответа сервера при получении игры.");
  }

  return data;
}
