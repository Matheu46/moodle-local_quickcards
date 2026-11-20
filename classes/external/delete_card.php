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
 * External API to delete a flashcard.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_card extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Card ID to delete.'),
        ]);
    }

    /**
     * Delete a card owned by current user.
     *
     * @param int $id Card ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        require_capability('local/quickcards:use', $context);
        \local_quickcards\manager::require_access();

        $card = $DB->get_record('local_quickcards_cards', ['id' => $params['id'], 'userid' => $USER->id], '*', MUST_EXIST);

        // Delete associated review record.
        $DB->delete_records('local_quickcards_reviews', ['cardid' => $card->id, 'userid' => $USER->id]);

        // Delete the card.
        $DB->delete_records('local_quickcards_cards', ['id' => $card->id, 'userid' => $USER->id]);

        return [
            'id' => (int) $card->id,
            'deckid' => (int) $card->deckid,
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
            'id' => new external_value(PARAM_INT, 'Deleted card ID.'),
            'deckid' => new external_value(PARAM_INT, 'Deck ID.'),
            'success' => new external_value(PARAM_BOOL, 'Whether deletion succeeded.'),
        ]);
    }
}
