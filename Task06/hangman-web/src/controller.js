import { HangmanGame } from './game.js';
import { View } from './view.js';

/**
 * Инициализация контроллера: связывает Game и View.
 */
function initController() {
  const game = new HangmanGame();
  const view = new View();

  function startGame(playerName) {
    game.startNewGame(playerName);
    const state = game.getState();

    view.updateGameState(state);
    view.setGameInputEnabled(true);
    view.setNewGameEnabled(true);
    view.setMessage(`Игра началась, ${state.playerName}! Введите букву.`);

    view.letterInput.focus();
  }

  function handleGuess(letter) {
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

    if (state.status === 'won') {
      view.setGameInputEnabled(false);
      view.setMessage(
        `Поздравляем, ${state.playerName}! Вы угадали слово "${state.secretWord}".`
      );
    } else if (state.status === 'lost') {
      view.setGameInputEnabled(false);
      view.setMessage(
        `Игра окончена, ${state.playerName}. Загаданное слово было "${state.secretWord}".`
      );
    }
  }

  function handleNewGame() {
    const state = game.getState();
    const name = view.getPlayerName() || state.playerName || 'Player';
    startGame(name);
  }

  // Привязка обработчиков
  view.bindStart(startGame);
  view.bindGuess(handleGuess);
  view.bindNewGame(handleNewGame);

  // Стартовое состояние
  view.setGameInputEnabled(false);
  view.setNewGameEnabled(false);
  view.setMessage('Введите имя и нажмите «Начать игру».');
}

/**
 * Раньше это было в main.js: инициализируем контроллер после загрузки DOM.
 */
document.addEventListener('DOMContentLoaded', () => {
  initController();
});
