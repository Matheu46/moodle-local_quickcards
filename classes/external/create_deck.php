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
 * External API to create a new flashcard deck.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_deck extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Deck name.'),
            'description' => new external_value(PARAM_RAW, 'Deck description.', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Associated course id or 0 for general.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create a new deck for current user.
     *
     * @param string $name Deck name.
     * @param string $description Deck description.
     * @param int $courseid Associated course id.
     * @return array
     */
    public static function execute(string $name, string $description = '', int $courseid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'name' => $name,
            'description' => $description,
            'courseid' => $courseid,
        ]);

        $trimmedname = trim($params['name']);
        if (empty($trimmedname)) {
            throw new invalid_parameter_exception('Deck name cannot be empty.');
        }

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/quickcards:use', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        \local_quickcards\manager::require_enabled();

        $now = time();
        $record = (object) [
            'userid' => (int) $USER->id,
            'courseid' => (int) $params['courseid'],
            'name' => $trimmedname,
            'description' => trim($params['description']),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $deckid = $DB->insert_record('local_quickcards_decks', $record);

        return [
            'id' => $deckid,
            'name' => $trimmedname,
            'description' => $record->description,
            'courseid' => $record->courseid,
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
            'id' => new external_value(PARAM_INT, 'New deck ID.'),
            'name' => new external_value(PARAM_TEXT, 'Deck name.'),
            'description' => new external_value(PARAM_RAW, 'Deck description.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded.'),
        ]);
    }
}
