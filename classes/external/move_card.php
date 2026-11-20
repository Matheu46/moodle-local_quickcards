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
 * External API to move a card to another deck owned by current user.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_card extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cardid' => new external_value(PARAM_INT, 'Card ID to move.'),
            'targetdeckid' => new external_value(PARAM_INT, 'Destination deck ID.'),
        ]);
    }

    /**
     * Move a card to another deck owned by current user.
     *
     * @param int $cardid Card ID.
     * @param int $targetdeckid Target deck ID.
     * @return array
     */
    public static function execute(int $cardid, int $targetdeckid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cardid' => $cardid,
            'targetdeckid' => $targetdeckid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        require_capability('local/quickcards:use', $context);
        \local_quickcards\manager::require_access();

        // Verify card exists and belongs to current user.
        $card = $DB->get_record(
            'local_quickcards_cards',
            ['id' => $params['cardid'], 'userid' => $USER->id],
            '*',
            MUST_EXIST
        );

        // Verify destination deck exists and belongs to current user.
        $targetdeck = $DB->get_record(
            'local_quickcards_decks',
            ['id' => $params['targetdeckid'], 'userid' => $USER->id],
            '*',
            MUST_EXIST
        );

        if ((int) $card->deckid === (int) $targetdeck->id) {
            throw new invalid_parameter_exception('Card is already in the target deck.');
        }

        $card->deckid = (int) $targetdeck->id;
        $card->timemodified = time();
        $DB->update_record('local_quickcards_cards', $card);

        return [
            'cardid' => (int) $card->id,
            'targetdeckid' => (int) $targetdeck->id,
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
            'cardid' => new external_value(PARAM_INT, 'Moved card ID.'),
            'targetdeckid' => new external_value(PARAM_INT, 'Destination deck ID.'),
            'success' => new external_value(PARAM_BOOL, 'Whether operation succeeded.'),
        ]);
    }
}
