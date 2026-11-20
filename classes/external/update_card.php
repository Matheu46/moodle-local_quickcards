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

namespace local_quickcards\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

/**
 * External API to update an existing flashcard.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_card extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Card ID to update.'),
            'question' => new external_value(PARAM_RAW, 'Question / Front content.'),
            'answer' => new external_value(PARAM_RAW, 'Answer / Back content.'),
        ]);
    }

    /**
     * Update a card owned by current user.
     *
     * @param int $id Card ID.
     * @param string $question Front content.
     * @param string $answer Back content.
     * @return array
     */
    public static function execute(int $id, string $question, string $answer): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'question' => $question,
            'answer' => $answer,
        ]);

        $trimmedq = trim($params['question']);
        $trimmeda = trim($params['answer']);
        if (empty($trimmedq)) {
            throw new invalid_parameter_exception('Card front (question) cannot be empty.');
        }
        if (empty($trimmeda)) {
            throw new invalid_parameter_exception('Card back (answer) cannot be empty.');
        }

        $context = context_system::instance();
        self::validate_context($context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        require_capability('local/quickcards:use', $context);
        \local_quickcards\manager::require_access();

        $card = $DB->get_record('local_quickcards_cards', ['id' => $params['id'], 'userid' => $USER->id], '*', MUST_EXIST);

        $card->question = $trimmedq;
        $card->answer = $trimmeda;
        $card->timemodified = time();

        $DB->update_record('local_quickcards_cards', $card);

        return [
            'id' => (int) $card->id,
            'deckid' => (int) $card->deckid,
            'question' => $card->question,
            'answer' => $card->answer,
            'success' => true,
        ];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Card ID.'),
            'deckid' => new external_value(PARAM_INT, 'Deck ID.'),
            'question' => new external_value(PARAM_RAW, 'Front question.'),
            'answer' => new external_value(PARAM_RAW, 'Back answer.'),
            'success' => new external_value(PARAM_BOOL, 'Whether operation succeeded.'),
        ]);
    }
}
