/**
 * Класс, инкапсулирующий игровую логику "Виселицы" без привязки к DOM.
 * В этой версии (как и в консольном приложении на PHP) список слов
 * и выбор случайного слова находятся внутри модуля Game.
 */

/**
 * Набор 6-буквенных английских слов.
 * Аналог массива слов из консольной версии.
 */
const WORDS = [
  'planet',
  'rocket',
  'socket',
  'object',
  'window',
  'screen',
  'python',
  'letter',
  'summer',
  'winter',
  'spring',
  'forest',
  'castle',
  'bridge',
  'driver',
  'school',
  'mother',
  'father',
  'little',
  'golden'
];

/**
 * Возвращает случайное слово из массива WORDS.
 */
function getRandomWord() {
  if (WORDS.length === 0) {
    throw new Error('Список слов пуст.');
  }
  const index = Math.floor(Math.random() * WORDS.length);
  return WORDS[index];
}

export class HangmanGame {
  constructor(maxErrors = 6) {
    this.maxErrors = maxErrors;
    this.playerName = '';
    this.secretWord = '';
    this.guessedLetters = [];
    this.errors = 0;
    this.status = 'idle'; // 'idle' | 'playing' | 'won' | 'lost'
  }

  /**
   * Начать новую игру.
   */
  startNewGame(playerName = 'Player') {
    this.playerName = playerName || 'Player';
    this.secretWord = getRandomWord().toLowerCase();
    this.guessedLetters = [];
    this.errors = 0;
    this.status = 'playing';
  }

  /**
   * Обработать попытку угадать букву.
   * Возвращает объект с информацией о попытке.
   *
   * @param {string} rawLetter
   * @returns {{ isNew: boolean, success: boolean, letter: string }}
   */
  guess(rawLetter) {
    if (this.status !== 'playing') {
      return { isNew: false, success: false, letter: '' };
    }

    if (!rawLetter) {
      return { isNew: false, success: false, letter: '' };
    }

    const letter = rawLetter.toLowerCase();

    if (!/^[a-z]$/.test(letter)) {
      return { isNew: false, success: false, letter };
    }

    const alreadyGuessed = this.guessedLetters.includes(letter);
    let success = false;

    if (!alreadyGuessed) {
      this.guessedLetters.push(letter);

      if (this.secretWord.includes(letter)) {
        success = true;
      } else {
        this.errors += 1;
      }

      this.updateStatus();
    } else {
      // Буква уже была, статус и счётчики не меняем
      success = this.secretWord.includes(letter);
    }

    return { isNew: !alreadyGuessed, success, letter };
  }

  /**
   * Пересчитать статус игры после новой попытки.
   */
  updateStatus() {
    if (this.errors >= this.maxErrors) {
      this.status = 'lost';
      return;
    }

    if (this.isWordGuessed()) {
      this.status = 'won';
    }
  }

  /**
   * Все ли буквы слова угаданы?
   */
  isWordGuessed() {
    if (!this.secretWord) {
      return false;
    }

    return Array.from(this.secretWord).every((ch) =>
      this.guessedLetters.includes(ch.toLowerCase())
    );
  }

  /**
   * Маска слова вида "_ _ a _ _ b".
   */
  getMaskedWord() {
    if (!this.secretWord) {
      return '—';
    }

    return Array.from(this.secretWord)
      .map((ch) => (this.guessedLetters.includes(ch) ? ch : '_'))
      .join(' ');
  }

  /**
   * Текущее состояние игры в виде простого объекта.
   */
  getState() {
    return {
      playerName: this.playerName,
      maskedWord: this.getMaskedWord(),
      errors: this.errors,
      maxErrors: this.maxErrors,
      guessedLetters: [...this.guessedLetters],
      status: this.status,
      secretWord: this.secretWord
    };
  }
}
