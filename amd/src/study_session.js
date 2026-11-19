// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Interactive study session controller with active recall and SM-2 ratings.
 *
 * @module      local_quickcards/study_session
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Initialize the interactive study session.
 *
 * @param {number|Array} deckidOrCards Deck ID or array of cards.
 * @param {string} backurl Return URL after completion.
 */
export const init = (deckidOrCards, backurl) => {
    let cardsQueue = [];

    // Attempt to load from JSON data script in DOM first.
    const dataEl = document.getElementById('quickcards-session-data');
    if (dataEl && dataEl.textContent) {
        try {
            cardsQueue = JSON.parse(dataEl.textContent);
        } catch (e) {
            cardsQueue = [];
        }
    }

    // Fallback if cards were passed directly as argument.
    if ((!cardsQueue || cardsQueue.length === 0) && Array.isArray(deckidOrCards)) {
        cardsQueue = [...deckidOrCards];
    } else if ((!cardsQueue || cardsQueue.length === 0) && deckidOrCards && Array.isArray(deckidOrCards.cards)) {
        cardsQueue = [...deckidOrCards.cards];
    }

    const totalInitialCount = cardsQueue.length;
    if (totalInitialCount === 0) {
        return;
    }

    let currentIndex = 0;
    let isFlipped = false;

    if (backurl) {
        const completedBackBtn = document.querySelector('#study-completed-container a');
        if (completedBackBtn) {
            completedBackBtn.setAttribute('href', backurl);
        }
    }

    // DOM Elements.
    const activeSessionEl = document.getElementById('study-active-session');
    const completedSessionEl = document.getElementById('study-completed-container');
    const questionContentEl = document.getElementById('study-question-content');
    const answerContentEl = document.getElementById('study-answer-content');
    const deckNameEl = document.getElementById('study-deck-name');
    const repCountEl = document.getElementById('study-rep-count');
    const frontActionsEl = document.getElementById('study-front-actions');
    const backSectionEl = document.getElementById('study-back-section');
    const showAnswerBtn = document.getElementById('study-show-answer-btn');
    const progressBarEl = document.getElementById('study-progress-bar');
    const progressTextEl = document.getElementById('study-progress-text');

    /**
     * Render the current card at the head of the queue.
     */
    const renderCurrentCard = () => {
        if (cardsQueue.length === 0) {
            // All cards completed!
            if (activeSessionEl) {
                activeSessionEl.classList.add('d-none');
            }
            if (completedSessionEl) {
                completedSessionEl.classList.remove('d-none');
            }
            if (progressBarEl) {
                progressBarEl.style.width = '100%';
            }
            return;
        }

        const card = cardsQueue[0];
        isFlipped = false;

        if (questionContentEl) {
            questionContentEl.innerHTML = card.question;
        }
        if (answerContentEl) {
            answerContentEl.innerHTML = card.answer;
        }
        if (deckNameEl) {
            deckNameEl.textContent = card.deckname || '';
        }
        if (repCountEl) {
            repCountEl.textContent = card.repetitions || 0;
        }

        // Show front, hide back.
        if (frontActionsEl) {
            frontActionsEl.classList.remove('d-none');
        }
        if (backSectionEl) {
            backSectionEl.classList.add('d-none');
        }

        // Update progress.
        const completedCount = Math.min(currentIndex, totalInitialCount);
        const percent = Math.min(100, Math.round((completedCount / totalInitialCount) * 100));
        if (progressBarEl) {
            progressBarEl.style.width = percent + '%';
        }
        if (progressTextEl) {
            getString('cardof', 'local_quickcards', {
                current: Math.min(completedCount + 1, totalInitialCount),
                total: totalInitialCount,
            }).then((str) => {
                progressTextEl.textContent = str;
                return true;
            }).catch(Notification.exception);
        }
    };

    /**
     * Flip card to show answer and evaluation ratings.
     */
    const flipCard = () => {
        if (isFlipped) {
            return;
        }
        isFlipped = true;
        if (frontActionsEl) {
            frontActionsEl.classList.add('d-none');
        }
        if (backSectionEl) {
            backSectionEl.classList.remove('d-none');
        }
    };

    /**
     * Submit user rating (1: Again, 2: Hard, 3: Good, 4: Easy).
     *
     * @param {number} rating User rating.
     */
    const submitRating = (rating) => {
        if (!isFlipped || cardsQueue.length === 0) {
            return;
        }

        const card = cardsQueue.shift();
        currentIndex++;

        // If card was failed (Again), requeue at the end of current study session.
        if (rating === 1) {
            cardsQueue.push(card);
        }

        // Call background WebService to update SM-2 schedule.
        fetchMany([{
            methodname: 'local_quickcards_submit_review',
            args: {
                cardid: card.id,
                rating: rating,
            },
        }])[0].catch(Notification.exception);

        // Advance to next card smoothly.
        renderCurrentCard();
    };

    // Click handler for Show Answer button.
    if (showAnswerBtn) {
        showAnswerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            flipCard();
        });
    }

    // Click handler for rating buttons.
    document.addEventListener('click', (e) => {
        const ratingBtn = e.target.closest('[data-action="rating"]');
        if (ratingBtn) {
            e.preventDefault();
            const rating = parseInt(ratingBtn.dataset.rating, 10);
            submitRating(rating);
        }
    });

    // Keyboard shortcuts listener.
    document.addEventListener('keydown', (e) => {
        // Ignore if user is inside an input, textarea or contenteditable.
        if (['INPUT', 'TEXTAREA'].includes(e.target.tagName) || e.target.isContentEditable) {
            return;
        }

        if (e.key === ' ' || e.key === 'Enter') {
            if (!isFlipped) {
                e.preventDefault();
                flipCard();
            }
        } else if (e.key === '1' && isFlipped) {
            e.preventDefault();
            submitRating(1);
        } else if (e.key === '2' && isFlipped) {
            e.preventDefault();
            submitRating(2);
        } else if (e.key === '3' && isFlipped) {
            e.preventDefault();
            submitRating(3);
        } else if (e.key === '4' && isFlipped) {
            e.preventDefault();
            submitRating(4);
        }
    });

    // Start with the first card immediately.
    renderCurrentCard();
};

export default {
    init: init,
};
