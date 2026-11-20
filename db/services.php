<?php
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
 * External service definitions for QuickCards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_quickcards_create_deck' => [
        'classname' => 'local_quickcards\\external\\create_deck',
        'methodname' => 'execute',
        'description' => 'Create a new flashcard deck for the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_update_deck' => [
        'classname' => 'local_quickcards\\external\\update_deck',
        'methodname' => 'execute',
        'description' => 'Update an existing flashcard deck for the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_delete_deck' => [
        'classname' => 'local_quickcards\\external\\delete_deck',
        'methodname' => 'execute',
        'description' => 'Delete a flashcard deck and its cards.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_create_card' => [
        'classname' => 'local_quickcards\\external\\create_card',
        'methodname' => 'execute',
        'description' => 'Create a new flashcard in a deck.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_update_card' => [
        'classname' => 'local_quickcards\\external\\update_card',
        'methodname' => 'execute',
        'description' => 'Update an existing flashcard.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_delete_card' => [
        'classname' => 'local_quickcards\\external\\delete_card',
        'methodname' => 'execute',
        'description' => 'Delete a flashcard and its review progress.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_submit_review' => [
        'classname' => 'local_quickcards\\external\\submit_review',
        'methodname' => 'execute',
        'description' => 'Submit a flashcard review rating and update SM-2 schedule.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_move_card' => [
        'classname' => 'local_quickcards\\external\\move_card',
        'methodname' => 'execute',
        'description' => 'Move a flashcard to another deck.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_reset_deck' => [
        'classname' => 'local_quickcards\\external\\reset_deck',
        'methodname' => 'execute',
        'description' => 'Reset study progress for all cards in a deck.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_import_deck' => [
        'classname' => 'local_quickcards\\external\\import_deck',
        'methodname' => 'execute',
        'description' => 'Import a flashcard deck from a JSON payload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_quickcards_import_csv' => [
        'classname' => 'local_quickcards\\external\\import_csv',
        'methodname' => 'execute',
        'description' => 'Import a flashcard deck from CSV or tab-separated text (Anki/Quizlet).',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
