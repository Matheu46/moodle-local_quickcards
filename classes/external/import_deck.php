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
use local_quickcards\importer;

/**
 * External API to import a flashcard deck from a JSON payload.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_deck extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jsoncontent' => new external_value(PARAM_RAW, 'Raw JSON export string.'),
            'courseid' => new external_value(PARAM_INT, 'Associated course ID (0 for general).', VALUE_DEFAULT, 0),
            'restoreprogress' => new external_value(PARAM_BOOL, 'Whether to restore SM-2 progress.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Import a flashcard deck for the current user.
     *
     * @param string $jsoncontent Raw JSON export string.
     * @param int $courseid Associated course ID.
     * @param bool $restoreprogress Whether to restore study progress.
     * @return array
     */
    public static function execute(string $jsoncontent, int $courseid = 0, bool $restoreprogress = false): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'jsoncontent' => $jsoncontent,
            'courseid' => $courseid,
            'restoreprogress' => $restoreprogress,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/quickcards:use', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $res = importer::import_deck_from_json(
            $params['jsoncontent'],
            (int) $USER->id,
            (int) $params['courseid'],
            (bool) $params['restoreprogress']
        );

        return [
            'deckid' => $res['deckid'],
            'deckname' => $res['deckname'],
            'cardscount' => $res['cardscount'],
            'progressrestored' => $res['progressrestored'],
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
            'deckid' => new external_value(PARAM_INT, 'ID of the newly created deck.'),
            'deckname' => new external_value(PARAM_TEXT, 'Name of the imported deck.'),
            'cardscount' => new external_value(PARAM_INT, 'Number of cards imported.'),
            'progressrestored' => new external_value(PARAM_BOOL, 'Whether study progress was restored.'),
            'success' => new external_value(PARAM_BOOL, 'Whether operation succeeded.'),
        ]);
    }
}
