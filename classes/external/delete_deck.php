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

/**
 * External API to delete a flashcard deck and its associated cards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_deck extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Deck ID to delete.'),
        ]);
    }

    /**
     * Delete a deck owned by current user.
     *
     * @param int $id Deck ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/quickcards:use', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        \local_quickcards\manager::require_access();

        $deck = $DB->get_record('local_quickcards_decks', ['id' => $params['id'], 'userid' => $USER->id], '*', MUST_EXIST);

        // Delete reviews for cards in this deck.
        $cardids = $DB->get_fieldset_select(
            'local_quickcards_cards',
            'id',
            'deckid = :deckid AND userid = :userid',
            ['deckid' => $deck->id, 'userid' => $USER->id]
        );

        if (!empty($cardids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_quickcards_reviews', "cardid $insql", $inparams);
        }

        // Delete cards in this deck.
        $DB->delete_records('local_quickcards_cards', ['deckid' => $deck->id, 'userid' => $USER->id]);

        // Delete the deck itself.
        $DB->delete_records('local_quickcards_decks', ['id' => $deck->id, 'userid' => $USER->id]);

        return [
            'id' => (int) $deck->id,
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
            'id' => new external_value(PARAM_INT, 'Deleted deck ID.'),
            'success' => new external_value(PARAM_BOOL, 'Whether deletion succeeded.'),
        ]);
    }
}
