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
 * External API to import a flashcard deck from CSV or tab-separated text (Anki/Quizlet).
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_csv extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'deckname' => new external_value(PARAM_TEXT, 'Name of the new deck.'),
            'csvcontent' => new external_value(PARAM_RAW, 'Raw CSV or tab-separated text content.'),
            'courseid' => new external_value(PARAM_INT, 'Associated course ID (0 for general).', VALUE_DEFAULT, 0),
            'delimiter' => new external_value(
                PARAM_ALPHA,
                'Delimiter mode: auto, tab, comma, or semicolon.',
                VALUE_DEFAULT,
                'auto'
            ),
        ]);
    }

    /**
     * Import a flashcard deck from CSV/TSV text for the current user.
     *
     * @param string $deckname Name of the new deck.
     * @param string $csvcontent Raw CSV/TSV content.
     * @param int $courseid Associated course ID.
     * @param string $delimiter Delimiter mode (auto, tab, comma, semicolon).
     * @return array
     */
    public static function execute(
        string $deckname,
        string $csvcontent,
        int $courseid = 0,
        string $delimiter = 'auto'
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'deckname' => $deckname,
            'csvcontent' => $csvcontent,
            'courseid' => $courseid,
            'delimiter' => $delimiter,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        require_capability('local/quickcards:use', $context);
        \local_quickcards\manager::require_access();

        $delimitermap = [
            'auto' => 'auto',
            'tab' => "\t",
            'comma' => ',',
            'semicolon' => ';',
        ];
        $actualdelimiter = $delimitermap[$params['delimiter']] ?? 'auto';

        $res = importer::import_deck_from_csv(
            $params['deckname'],
            $params['csvcontent'],
            (int) $USER->id,
            (int) $params['courseid'],
            $actualdelimiter
        );

        return [
            'deckid' => $res['deckid'],
            'deckname' => $res['deckname'],
            'cardscount' => $res['cardscount'],
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
            'success' => new external_value(PARAM_BOOL, 'Whether operation succeeded.'),
        ]);
    }
}
