/**
 * Класс View отвечает за работу с DOM:
 * - отображение текущего состояния игры,
 * - вывод списка игр и информации о повторе (IndexedDB),
 * - обработка пользовательского ввода.
 */

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

export class View {
  constructor() {
    // Игровые элементы
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

    // Элементы истории / IndexedDB
    this.showGamesButton = document.getElementById('showGamesButton');
    this.gamesListElement = document.getElementById('gamesList');
    this.replayForm = document.getElementById('replayForm');
    this.replayIdInput = document.getElementById('replayId');
    this.replayInfoElement = document.getElementById('replayInfo');

    this.renderGallows(0);
  }

  // === Привязка обработчиков ===

  /**
   * Старт новой игры (кнопка "Начать игру" + Enter в поле имени).
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
   * Попытка угадать букву.
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
   * Кнопка "Новая игра".
   */
  bindNewGame(handler) {
    this.newGameButton.addEventListener('click', () => {
      handler();
    });
  }

  /**
   * Кнопка "Показать все сохранённые игры".
   */
  bindShowGames(handler) {
    this.showGamesButton.addEventListener('click', () => {
      handler();
    });
  }

  /**
   * Форма повтора игры по ID.
   */
  bindReplay(handler) {
    this.replayForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const value = this.replayIdInput.value.trim();
      const id = Number.parseInt(value, 10);
      handler(id);
    });
  }

  // === Отображение состояния игры ===

  setGameInputEnabled(enabled) {
    this.letterInput.disabled = !enabled;
    this.guessButton.disabled = !enabled;
  }

  setNewGameEnabled(enabled) {
    this.newGameButton.disabled = !enabled;
  }

  focusLetterInput() {
    this.letterInput.focus();
  }

  /**
   * Обновить визуальное состояние игры.
   */
  updateGameState(state) {
    this.maskedWordElement.textContent = state.maskedWord || '—';
    this.errorsElement.textContent = `${state.errors} / ${state.maxErrors}`;
    this.guessedLettersElement.textContent =
      state.guessedLetters.length > 0 ? state.guessedLetters.join(', ') : '—';

    this.renderGallows(state.errors);
  }

  setMessage(text) {
    this.messageElement.textContent = text;
  }

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

  // === История игр / IndexedDB ===

  /**
   * Отрисовать список игр в виде таблицы.
   * @param {Array<{id:number, playedAt?:string, playerName:string, secretWord:string, result:string}>} games
   */
  renderGamesList(games) {
    if (!this.gamesListElement) {
      return;
    }

    if (!games || games.length === 0) {
      this.gamesListElement.classList.add('games-list--empty');
      this.gamesListElement.innerHTML =
        '<p class="hint">В базе пока нет сохранённых игр.</p>';
      return;
    }

    this.gamesListElement.classList.remove('games-list--empty');

    const rows = games
      .map((g) => {
        let dateStr = '';
        if (g.playedAt) {
          const d = new Date(g.playedAt);
          if (!Number.isNaN(d.getTime())) {
            dateStr = d.toLocaleString();
          }
        }

        const result = (g.result || '').toLowerCase();
        let resultLabel = 'IN PROGRESS';
        let resultClass = 'tag--inprogress';

        if (result === 'win') {
          resultLabel = 'WIN';
          resultClass = 'tag--win';
        } else if (result === 'lose') {
          resultLabel = 'LOSE';
          resultClass = 'tag--lose';
        }

        return `
          <tr>
            <td class="games-table__id">${g.id}</td>
            <td>${escapeHtml(g.playerName ?? '')}</td>
            <td>${escapeHtml(g.secretWord ?? '')}</td>
            <td>${escapeHtml(dateStr)}</td>
            <td class="games-table__result">
              <span class="tag ${resultClass}">${resultLabel}</span>
            </td>
          </tr>
        `;
      })
      .join('');

    this.gamesListElement.innerHTML = `
      <table class="games-table">
        <thead>
          <tr>
            <th class="games-table__id">ID</th>
            <th>Игрок</th>
            <th>Слово</th>
            <th>Дата и время</th>
            <th class="games-table__result">Результат</th>
          </tr>
        </thead>
        <tbody>
          ${rows}
        </tbody>
      </table>
    `;
  }

  /**
   * Сообщение в блоке "История партий".
   */
  setReplayInfo(text) {
    if (this.replayInfoElement) {
      this.replayInfoElement.textContent = text;
    }
  }
}
