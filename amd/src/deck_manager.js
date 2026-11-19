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
 * Deck and Card management module for QuickCards.
 *
 * @module      local_quickcards/deck_manager
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';
import {Modal} from 'bootstrap';

/**
 * Get or create a Bootstrap modal instance.
 *
 * @param {HTMLElement} element Modal DOM element.
 * @return {Modal|null} Bootstrap Modal instance.
 */
const getModalInstance = (element) => {
    if (!element) {
        return null;
    }
    return Modal.getOrCreateInstance(element);
};

/**
 * Initialize Deck Manager event listeners.
 */
export const init = () => {
    const deckModalEl = document.getElementById('quickcards-deck-modal');
    const cardModalEl = document.getElementById('quickcards-card-modal');
    const moveCardModalEl = document.getElementById('quickcards-move-card-modal');

    // Deck form fields.
    const deckForm = document.getElementById('quickcards-deck-form');
    const deckIdInput = document.getElementById('quickcards-deck-id');
    const deckNameInput = document.getElementById('quickcards-deck-name');
    const deckDescInput = document.getElementById('quickcards-deck-description');
    const deckCourseSelect = document.getElementById('quickcards-deck-course');
    const deckModalTitle = document.getElementById('quickcards-deck-modal-title');

    // Card form fields.
    const cardForm = document.getElementById('quickcards-card-form');
    const cardIdInput = document.getElementById('quickcards-card-id');
    const cardDeckIdInput = document.getElementById('quickcards-card-deckid');
    const cardQuestionInput = document.getElementById('quickcards-card-question');
    const cardAnswerInput = document.getElementById('quickcards-card-answer');
    const cardModalTitle = document.getElementById('quickcards-card-modal-title');

    // Move Card form fields.
    const moveCardForm = document.getElementById('quickcards-move-card-form');
    const moveCardIdInput = document.getElementById('quickcards-move-card-id');
    const moveTargetDeckSelect = document.getElementById('quickcards-move-target-deck');

    // Document-level click handler for actions.
    document.addEventListener('click', (e) => {
        const createDeckBtn = e.target.closest('[data-action="quickcards-create-deck"]');
        if (createDeckBtn) {
            if (deckForm) {
                deckForm.reset();
                deckIdInput.value = '0';
                getString('createdeck', 'local_quickcards').then((title) => {
                    if (deckModalTitle) {
                        deckModalTitle.textContent = title;
                    }
                    const modal = getModalInstance(deckModalEl);
                    if (modal) {
                        modal.show();
                    }
                    return true;
                }).catch(Notification.exception);
            }
            return;
        }

        const editDeckBtn = e.target.closest('[data-action="quickcards-edit-deck"]');
        if (editDeckBtn) {
            e.preventDefault();
            const id = editDeckBtn.dataset.id;
            const name = editDeckBtn.dataset.name || '';
            const desc = editDeckBtn.dataset.description || '';
            const courseid = editDeckBtn.dataset.courseid || '0';

            if (deckForm) {
                deckIdInput.value = id;
                deckNameInput.value = name;
                deckDescInput.value = desc;
                if (deckCourseSelect) {
                    deckCourseSelect.value = courseid;
                }

                getString('editdeck', 'local_quickcards').then((title) => {
                    if (deckModalTitle) {
                        deckModalTitle.textContent = title;
                    }
                    const modal = getModalInstance(deckModalEl);
                    if (modal) {
                        modal.show();
                    }
                    return true;
                }).catch(Notification.exception);
            }
            return;
        }

        const deleteDeckBtn = e.target.closest('[data-action="quickcards-delete-deck"]');
        if (deleteDeckBtn) {
            e.preventDefault();
            const id = parseInt(deleteDeckBtn.dataset.id, 10);

            getStrings([
                {key: 'deletedeck', component: 'local_quickcards'},
                {key: 'confirmdeletedeck', component: 'local_quickcards'},
                {key: 'delete', component: 'local_quickcards'},
                {key: 'cancel', component: 'local_quickcards'},
            ]).then(([title, question, deleteLabel, cancelLabel]) => {
                Notification.confirm(
                    title,
                    question,
                    deleteLabel,
                    cancelLabel,
                    () => {
                        fetchMany([{
                            methodname: 'local_quickcards_delete_deck',
                            args: {id: id},
                        }])[0].then((res) => {
                            if (res.success) {
                                window.location.href = M.cfg.wwwroot + '/local/quickcards/view.php';
                            }
                            return true;
                        }).catch(Notification.exception);
                    }
                );
                return true;
            }).catch(Notification.exception);
            return;
        }

        const createCardBtn = e.target.closest('[data-action="quickcards-create-card"]');
        if (createCardBtn) {
            const deckid = createCardBtn.dataset.deckid;
            if (cardForm) {
                cardForm.reset();
                cardIdInput.value = '0';
                cardDeckIdInput.value = deckid;

                getString('addcard', 'local_quickcards').then((title) => {
                    if (cardModalTitle) {
                        cardModalTitle.textContent = title;
                    }
                    const modal = getModalInstance(cardModalEl);
                    if (modal) {
                        modal.show();
                    }
                    return true;
                }).catch(Notification.exception);
            }
            return;
        }

        const editCardBtn = e.target.closest('[data-action="quickcards-edit-card"]');
        if (editCardBtn) {
            e.preventDefault();
            const id = editCardBtn.dataset.id;
            const question = editCardBtn.dataset.question || '';
            const answer = editCardBtn.dataset.answer || '';

            if (cardForm) {
                cardIdInput.value = id;
                cardQuestionInput.value = question;
                cardAnswerInput.value = answer;

                getString('editcard', 'local_quickcards').then((title) => {
                    if (cardModalTitle) {
                        cardModalTitle.textContent = title;
                    }
                    const modal = getModalInstance(cardModalEl);
                    if (modal) {
                        modal.show();
                    }
                    return true;
                }).catch(Notification.exception);
            }
            return;
        }

        const deleteCardBtn = e.target.closest('[data-action="quickcards-delete-card"]');
        if (deleteCardBtn) {
            e.preventDefault();
            const id = parseInt(deleteCardBtn.dataset.id, 10);

            getStrings([
                {key: 'deletecard', component: 'local_quickcards'},
                {key: 'confirmdeletecard', component: 'local_quickcards'},
                {key: 'delete', component: 'local_quickcards'},
                {key: 'cancel', component: 'local_quickcards'},
            ]).then(([title, question, deleteLabel, cancelLabel]) => {
                Notification.confirm(
                    title,
                    question,
                    deleteLabel,
                    cancelLabel,
                    () => {
                        fetchMany([{
                            methodname: 'local_quickcards_delete_card',
                            args: {id: id},
                        }])[0].then((res) => {
                            if (res.success) {
                                const cardEl = document.getElementById('card-item-' + id);
                                if (cardEl) {
                                    cardEl.remove();
                                } else {
                                    window.location.reload();
                                }
                            }
                            return true;
                        }).catch(Notification.exception);
                    }
                );
                return true;
            }).catch(Notification.exception);
            return;
        }

        const moveCardBtn = e.target.closest('[data-action="quickcards-move-card"]');
        if (moveCardBtn) {
            const id = moveCardBtn.dataset.id;
            if (moveCardIdInput) {
                moveCardIdInput.value = id;
            }
            const modal = getModalInstance(moveCardModalEl);
            if (modal) {
                modal.show();
            }
        }
    });

    // Submit Deck Form.
    if (deckForm) {
        deckForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const id = parseInt(deckIdInput.value, 10);
            const name = deckNameInput.value.trim();
            const description = deckDescInput ? deckDescInput.value.trim() : '';
            const courseid = deckCourseSelect ? parseInt(deckCourseSelect.value, 10) : 0;

            if (!name) {
                return;
            }

            const isNew = (id === 0);
            const method = isNew ? 'local_quickcards_create_deck' : 'local_quickcards_update_deck';
            const args = isNew ? {name, description, courseid} : {id, name, description, courseid};

            fetchMany([{
                methodname: method,
                args: args,
            }])[0].then((res) => {
                if (res.success) {
                    const modal = getModalInstance(deckModalEl);
                    if (modal) {
                        modal.hide();
                    }
                    if (isNew) {
                        window.location.href = M.cfg.wwwroot + '/local/quickcards/deck.php?id=' + res.id;
                    } else {
                        window.location.reload();
                    }
                }
                return true;
            }).catch(Notification.exception);
        });
    }

    // Submit Card Form.
    if (cardForm) {
        cardForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const id = parseInt(cardIdInput.value, 10);
            const deckid = parseInt(cardDeckIdInput.value, 10);
            const question = cardQuestionInput.value.trim();
            const answer = cardAnswerInput.value.trim();

            if (!question || !answer) {
                return;
            }

            const isNew = (id === 0);
            const method = isNew ? 'local_quickcards_create_card' : 'local_quickcards_update_card';
            const args = isNew ? {deckid, question, answer} : {id, question, answer};

            fetchMany([{
                methodname: method,
                args: args,
            }])[0].then((res) => {
                if (res.success) {
                    const modal = getModalInstance(cardModalEl);
                    if (modal) {
                        modal.hide();
                    }
                    window.location.reload();
                }
                return true;
            }).catch(Notification.exception);
        });
    }

    // Submit Move Card Form.
    if (moveCardForm) {
        moveCardForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const cardid = parseInt(moveCardIdInput.value, 10);
            const targetdeckid = moveTargetDeckSelect ? parseInt(moveTargetDeckSelect.value, 10) : 0;

            if (!cardid || !targetdeckid) {
                return;
            }

            fetchMany([{
                methodname: 'local_quickcards_move_card',
                args: {cardid, targetdeckid},
            }])[0].then((res) => {
                if (res.success) {
                    const modal = getModalInstance(moveCardModalEl);
                    if (modal) {
                        modal.hide();
                    }
                    const cardEl = document.getElementById('card-item-' + cardid);
                    if (cardEl) {
                        cardEl.remove();
                    } else {
                        window.location.reload();
                    }
                }
                return true;
            }).catch(Notification.exception);
        });
    }
};

export default {
    init: init,
};
