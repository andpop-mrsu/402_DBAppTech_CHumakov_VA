/**
 * Модуль работы с IndexedDB.
 * Здесь только получение/изменение/сохранение данных — без логики игры и без DOM.
 */

const DB_NAME = 'hangman-indexeddb';
const DB_VERSION = 1;
const GAMES_STORE = 'games';
const ATTEMPTS_STORE = 'attempts';

let dbPromise = null;

function openDb() {
  if (!dbPromise) {
    dbPromise = new Promise((resolve, reject) => {
      if (!('indexedDB' in window)) {
        reject(new Error('Ваш браузер не поддерживает IndexedDB.'));
        return;
      }

      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = () => {
        const db = request.result;

        if (!db.objectStoreNames.contains(GAMES_STORE)) {
          const games = db.createObjectStore(GAMES_STORE, {
            keyPath: 'id',
            autoIncrement: true
          });
          games.createIndex('by_played_at', 'playedAt');
        }

        if (!db.objectStoreNames.contains(ATTEMPTS_STORE)) {
          const attempts = db.createObjectStore(ATTEMPTS_STORE, {
            keyPath: 'id',
            autoIncrement: true
          });
          attempts.createIndex('by_game_id', 'gameId');
        }
      };

      request.onsuccess = () => {
        resolve(request.result);
      };

      request.onerror = () => {
        reject(request.error || new Error('Ошибка открытия базы IndexedDB.'));
      };
    });
  }

  return dbPromise;
}

function requestToPromise(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () =>
      reject(request.error || new Error('Ошибка запроса IndexedDB.'));
  });
}

/**
 * Создать новую игру в базе.
 * @returns {Promise<number>} ID созданной игры
 */
export async function saveNewGame({ playerName, secretWord, playedAt }) {
  const db = await openDb();
  const tx = db.transaction(GAMES_STORE, 'readwrite');
  const store = tx.objectStore(GAMES_STORE);

  const game = {
    playerName,
    secretWord,
    playedAt,
    result: 'in-progress'
  };

  const id = await requestToPromise(store.add(game));
  return id;
}

/**
 * Обновить результат игры (win/lose).
 */
export async function finishGame(gameId, result) {
  const db = await openDb();
  const tx = db.transaction(GAMES_STORE, 'readwrite');
  const store = tx.objectStore(GAMES_STORE);

  const existing = await requestToPromise(store.get(gameId));
  if (!existing) {
    return;
  }

  existing.result = result;
  await requestToPromise(store.put(existing));
}

/**
 * Сохранить попытку игрока.
 */
export async function saveAttempt({ gameId, attemptNo, letter, success }) {
  const db = await openDb();
  const tx = db.transaction(ATTEMPTS_STORE, 'readwrite');
  const store = tx.objectStore(ATTEMPTS_STORE);

  const attempt = {
    gameId,
    attemptNo,
    letter,
    success: success ? 1 : 0
  };

  await requestToPromise(store.add(attempt));
}

/**
 * Получить список всех игр.
 */
export async function getAllGames() {
  const db = await openDb();
  const tx = db.transaction(GAMES_STORE, 'readonly');
  const store = tx.objectStore(GAMES_STORE);

  let games = [];
  if ('getAll' in store) {
    games = await requestToPromise(store.getAll());
  } else {
    games = await new Promise((resolve, reject) => {
      const result = [];
      const request = store.openCursor();
      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (cursor) {
          result.push(cursor.value);
          cursor.continue();
        } else {
          resolve(result);
        }
      };
      request.onerror = () =>
        reject(request.error || new Error('Ошибка чтения игр.'));
    });
  }

  games.sort((a, b) => {
    // сортируем по id по убыванию, чтобы новые игры были выше
    return (b.id || 0) - (a.id || 0);
  });

  return games;
}

/**
 * Получить игру и её попытки по ID.
 * @returns {Promise<{game: any, attempts: any[]} | null>}
 */
export async function getGameWithAttempts(gameId) {
  const db = await openDb();
  const tx = db.transaction([GAMES_STORE, ATTEMPTS_STORE], 'readonly');
  const gamesStore = tx.objectStore(GAMES_STORE);
  const attemptsStore = tx.objectStore(ATTEMPTS_STORE);

  const game = await requestToPromise(gamesStore.get(gameId));
  if (!game) {
    return null;
  }

  const index = attemptsStore.index('by_game_id');

  let attempts = [];
  if ('getAll' in index) {
    attempts = await requestToPromise(index.getAll(gameId));
  } else {
    attempts = await new Promise((resolve, reject) => {
      const result = [];
      const request = index.openCursor(IDBKeyRange.only(gameId));
      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (cursor) {
          result.push(cursor.value);
          cursor.continue();
        } else {
          resolve(result);
        }
      };
      request.onerror = () =>
        reject(request.error || new Error('Ошибка чтения попыток.'));
    });
  }

  attempts.sort((a, b) => (a.attemptNo || 0) - (b.attemptNo || 0));

  return { game, attempts };
}
