import { HangmanGame } from './game.js';
import { View } from './view.js';
import * as db from './db.js';

/**
 * Контроллер: связывает игровую логику, IndexedDB и представление.
 */
function initController() {
  const game = new HangmanGame();
  const view = new View();

  let currentGameId = null;
  let attemptNo = 0;
  let isReplaying = false;

  /**
   * Старт новой игры с сохранением в IndexedDB.
   */
  async function startGame(playerName) {
    isReplaying = false;

    game.startNewGame(playerName);
    const state = game.getState();

    view.updateGameState(state);
    view.setGameInputEnabled(false);
    view.setNewGameEnabled(false);
    view.setMessage('Создаём запись игры в IndexedDB...');

    try {
      const id = await db.saveNewGame({
        playerName: state.playerName,
        secretWord: state.secretWord,
        playedAt: new Date().toISOString()
      });

      currentGameId = id;
      attemptNo = 0;

      view.setGameInputEnabled(true);
      view.setNewGameEnabled(true);
      view.setMessage(`Игра началась, ${state.playerName}! Введите букву.`);
      view.focusLetterInput();
    } catch (error) {
      console.error(error);
      currentGameId = null;
      attemptNo = 0;

      view.setGameInputEnabled(true);
      view.setNewGameEnabled(true);
      view.setMessage(
        'Не удалось сохранить игру в IndexedDB. Игра будет продолжена без сохранения.'
      );
      view.focusLetterInput();
    }
  }

  /**
   * Обработка попытки угадать букву.
   */
  async function handleGuess(letter) {
    if (isReplaying) {
      view.setMessage('Сейчас воспроизводится сохранённая партия.');
      return;
    }

    if (!letter) {
      view.setMessage('Введите одну латинскую букву (a–z).');
      return;
    }

    const attempt = game.guess(letter);
    const state = game.getState();

    view.updateGameState(state);

    const normalizedLetter = attempt.letter || letter.toLowerCase();

    if (!/^[a-z]$/.test(normalizedLetter)) {
      view.setMessage('Введите одну латинскую букву (a–z).');
      return;
    }

    if (!attempt.isNew) {
      view.setMessage(`Буква "${normalizedLetter}" уже была использована.`);
    } else if (attempt.success) {
      view.setMessage(`Буква "${normalizedLetter}" есть в слове.`);
    } else {
      view.setMessage(`Буквы "${normalizedLetter}" нет в слове.`);
    }

    // Сохраняем попытку в IndexedDB, если есть активная игра
    if (currentGameId != null && attempt.isNew) {
      attemptNo += 1;
      try {
        await db.saveAttempt({
          gameId: currentGameId,
          attemptNo,
          letter: normalizedLetter,
          success: attempt.success
        });
      } catch (error) {
        console.error(error);
        // Игру не прерываем, просто не сохраняем попытку
      }
    }

    if (state.status === 'won') {
      view.setGameInputEnabled(false);
      view.setMessage(
        `Поздравляем, ${state.playerName}! Вы угадали слово "${state.secretWord}".`
      );

      if (currentGameId != null) {
        try {
          await db.finishGame(currentGameId, 'win');
        } catch (error) {
          console.error(error);
        }
      }
    } else if (state.status === 'lost') {
      view.setGameInputEnabled(false);
      view.setMessage(
        `Игра окончена, ${state.playerName}. Загаданное слово было "${state.secretWord}".`
      );

      if (currentGameId != null) {
        try {
          await db.finishGame(currentGameId, 'lose');
        } catch (error) {
          console.error(error);
        }
      }
    }
  }

  /**
   * Новая игра по кнопке "Новая игра".
   */
  function handleNewGame() {
    isReplaying = false;
    const currentName = view.getPlayerName() || game.getState().playerName || 'Player';
    startGame(currentName);
  }

  /**
   * Показать список всех сохранённых игр.
   */
  async function handleShowGames() {
    try {
      const games = await db.getAllGames();
      view.renderGamesList(games);

      if (!games || games.length === 0) {
        view.setReplayInfo('В IndexedDB пока нет сохранённых игр.');
      } else {
        view.setReplayInfo(
          `Загружено игр: ${games.length}. Введите ID нужной игры и нажмите «Повторить игру».`
        );
      }
    } catch (error) {
      console.error(error);
      view.setReplayInfo('Ошибка при чтении списка игр из IndexedDB.');
    }
  }

  /**
   * Повтор сохранённой игры по ID.
   */
  async function handleReplay(id) {
    if (!Number.isInteger(id) || id <= 0) {
      view.setReplayInfo('Введите корректный ID игры (целое число > 0).');
      return;
    }

    try {
      const data = await db.getGameWithAttempts(id);
      if (!data) {
        view.setReplayInfo(`Игра с ID = ${id} не найдена в IndexedDB.`);
        return;
      }

      const { game: savedGame, attempts } = data;

      if (!attempts || attempts.length === 0) {
        game.loadFromSavedGame(savedGame.playerName, savedGame.secretWord);
        view.updateGameState(game.getState());
        view.setReplayInfo(
          `Игра #${savedGame.id} для игрока ${savedGame.playerName} не содержит сохранённых ходов.`
        );
        return;
      }

      // Готовим игру к воспроизведению
      isReplaying = true;
      currentGameId = null; // при повторе ничего не сохраняем
      view.setGameInputEnabled(false);

      game.loadFromSavedGame(savedGame.playerName, savedGame.secretWord);
      view.updateGameState(game.getState());
      view.setReplayInfo(
        `Повтор игры #${savedGame.id} для игрока ${savedGame.playerName} начался.`
      );

      const sortedAttempts = [...attempts].sort(
        (a, b) => (a.attemptNo || 0) - (b.attemptNo || 0)
      );

      let index = 0;

      function step() {
        if (!isReplaying) {
          return; // повтор был прерван (например, нажата "Новая игра")
        }

        if (index >= sortedAttempts.length) {
          const finalState = game.getState();
          const finalResult =
            (savedGame.result || '').toLowerCase() || finalState.status;

          if (finalResult === 'win' || finalState.status === 'won') {
            view.setReplayInfo(
              `Повтор игры #${savedGame.id} завершён: победа. Слово "${savedGame.secretWord}".`
            );
          } else {
            view.setReplayInfo(
              `Повтор игры #${savedGame.id} завершён: поражение. Слово "${savedGame.secretWord}".`
            );
          }

          isReplaying = false;
          return;
        }

        const att = sortedAttempts[index++];
        game.guess(att.letter);
        const st = game.getState();
        view.updateGameState(st);

        const desc = att.success ? 'угадал' : 'ошибка';
        view.setReplayInfo(
          `Игра #${savedGame.id}, ход ${att.attemptNo}: буква "${att.letter}" — ${desc}.`
        );

        setTimeout(step, 600);
      }

      step();
    } catch (error) {
      console.error(error);
      isReplaying = false;
      view.setReplayInfo('Ошибка при чтении данных игры из IndexedDB.');
    }
  }

  // Привязка обработчиков
  view.bindStart((name) => {
    startGame(name);
  });
  view.bindGuess((letter) => {
    handleGuess(letter);
  });
  view.bindNewGame(() => {
    handleNewGame();
  });
  view.bindShowGames(() => {
    handleShowGames();
  });
  view.bindReplay((id) => {
    handleReplay(id);
  });

  // Стартовое состояние
  view.setGameInputEnabled(false);
  view.setNewGameEnabled(false);
  view.setMessage('Введите имя и нажмите «Начать игру».');
  view.setReplayInfo(
    'Здесь будут отображаться сохранённые партии из IndexedDB и информация о повторах.'
  );
}

document.addEventListener('DOMContentLoaded', () => {
  initController();
});
