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
use local_quickcards\sm2;

/**
 * External API to reset study progress for all cards in a deck.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reset_deck extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Deck ID to reset.'),
        ]);
    }

    /**
     * Reset review intervals and repetition counts for all cards in a deck.
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

        // Verify deck exists and belongs to current user.
        $deck = $DB->get_record('local_quickcards_decks', ['id' => $params['id'], 'userid' => $USER->id], '*', MUST_EXIST);

        $now = time();

        // Get all cards in this deck.
        $cards = $DB->get_records('local_quickcards_cards', ['deckid' => $deck->id, 'userid' => $USER->id], '', 'id');
        $cardcount = count($cards);

        if (!empty($cards)) {
            $cardids = array_keys($cards);
            [$insql, $inparams] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);

            // Fetch existing review records.
            $reviews = $DB->get_records_select('local_quickcards_reviews', "cardid $insql AND userid = :ruserid", [
                'ruserid' => $USER->id,
            ] + $inparams);

            $existingcardids = [];
            foreach ($reviews as $rev) {
                $existingcardids[] = $rev->cardid;
                $rev->easefactor = sm2::DEFAULT_EASE_FACTOR;
                $rev->intervaldays = 0;
                $rev->repetitions = 0;
                $rev->nextreview = $now;
                $rev->lastreview = null;
                $DB->update_record('local_quickcards_reviews', $rev);
            }

            // Insert default review record for any card that didn't have one yet.
            foreach ($cardids as $cid) {
                if (!in_array($cid, $existingcardids)) {
                    $newrev = (object) [
                        'cardid' => $cid,
                        'userid' => (int) $USER->id,
                        'easefactor' => sm2::DEFAULT_EASE_FACTOR,
                        'intervaldays' => 0,
                        'repetitions' => 0,
                        'nextreview' => $now,
                        'lastreview' => null,
                    ];
                    $DB->insert_record('local_quickcards_reviews', $newrev);
                }
            }
        }

        return [
            'id' => (int) $deck->id,
            'cardsreset' => $cardcount,
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
            'id' => new external_value(PARAM_INT, 'Reset deck ID.'),
            'cardsreset' => new external_value(PARAM_INT, 'Number of cards reset.'),
            'success' => new external_value(PARAM_BOOL, 'Whether operation succeeded.'),
        ]);
    }
}
