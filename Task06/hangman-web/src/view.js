/**
 * Класс View отвечает за работу с DOM: вывод состояния игры и обработку пользовательского ввода.
 */
export class View {
  constructor() {
    this.playerNameInput = document.getElementById('playerName');
    this.startButton = document.getElementById('startButton');
    this.newGameButton = document.getElementById('newGameButton');
    this.letterInput = document.getElementById('letterInput');
    this.guessButton = document.getElementById('guessButton');
    this.guessForm = document.getElementById('guessForm');

    this.gallowsElement = document.getElementById('gallows');
    this.maskedWordElement = document.getElementById('maskedWord');
    this.errorsElement = document.getElementById('errors');
    this.guessedLettersElement = document.getElementById('guessedLetters');
    this.messageElement = document.getElementById('message');

    this.renderGallows(0);
  }

  /**
   * Привязка обработчика к кнопке "Начать игру".
   * @param {(playerName: string) => void} handler
   */
  bindStart(handler) {
    this.startButton.addEventListener('click', () => {
      const name = this.playerNameInput.value.trim() || 'Player';
      handler(name);
    });

    this.playerNameInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const name = this.playerNameInput.value.trim() || 'Player';
        handler(name);
      }
    });
  }

  /**
   * Привязка обработчика к форме "Угадать букву".
   * @param {(letter: string) => void} handler
   */
  bindGuess(handler) {
    const submit = () => {
      const letter = this.letterInput.value.trim();
      this.letterInput.value = '';
      handler(letter);
      this.letterInput.focus();
    };

    this.guessForm.addEventListener('submit', (event) => {
      event.preventDefault();
      submit();
    });
  }

  /**
   * Привязка обработчика к кнопке "Новая игра".
   * @param {() => void} handler
   */
  bindNewGame(handler) {
    this.newGameButton.addEventListener('click', () => {
      handler();
    });
  }

  /**
   * Включить/отключить ввод букв.
   */
  setGameInputEnabled(enabled) {
    this.letterInput.disabled = !enabled;
    this.guessButton.disabled = !enabled;
  }

  /**
   * Включить/отключить кнопку "Новая игра".
   */
  setNewGameEnabled(enabled) {
    this.newGameButton.disabled = !enabled;
  }

  /**
   * Обновить визуальное состояние игры.
   * @param {{maskedWord:string, errors:number, maxErrors:number, guessedLetters:string[], status:string}} state
   */
  updateGameState(state) {
    this.maskedWordElement.textContent = state.maskedWord || '—';
    this.errorsElement.textContent = `${state.errors} / ${state.maxErrors}`;
    this.guessedLettersElement.textContent =
      state.guessedLetters.length > 0 ? state.guessedLetters.join(', ') : '—';

    this.renderGallows(state.errors);
  }

  /**
   * Показать текстовое сообщение игроку.
   */
  setMessage(text) {
    this.messageElement.textContent = text;
  }

  /**
   * Вернуть текущее имя игрока из поля ввода.
   */
  getPlayerName() {
    return this.playerNameInput.value.trim();
  }

  /**
   * Нарисовать виселицу в зависимости от количества ошибок.
   */
  renderGallows(errorsCount) {
    const states = [
      ['+---+', '    |', '    |', '    |', '   ==='],
      ['+---+', '0   |', '    |', '    |', '   ==='],
      ['+---+', '0   |', '|   |', '    |', '   ==='],
      ['+---+', '0   |', '/|  |', '    |', '   ==='],
      ['+---+', '0   |', '/|\\ |', '    |', '   ==='],
      ['+---+', '0   |', '/|\\ |', '/   |', '   ==='],
      ['+---+', '0   |', '/|\\ |', '/ \\ |', '   ===']
    ];

    const index = Math.max(0, Math.min(errorsCount, states.length - 1));
    this.gallowsElement.textContent = states[index].join('\n');
  }
}
