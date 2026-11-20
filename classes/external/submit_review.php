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
use local_quickcards\sm2;

/**
 * External API to record a flashcard review rating and update SM-2 schedule.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_review extends external_api {
    /**
     * Define the parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cardid' => new external_value(PARAM_INT, 'Card ID being reviewed.'),
            'rating' => new external_value(PARAM_INT, 'Review rating: 1 (Again), 2 (Hard), 3 (Good), 4 (Easy).'),
        ]);
    }

    /**
     * Submit a review for a card owned by current user.
     *
     * @param int $cardid Card ID.
     * @param int $rating Review rating (1-4).
     * @return array
     */
    public static function execute(int $cardid, int $rating): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cardid' => $cardid,
            'rating' => $rating,
        ]);

        if ($params['rating'] < sm2::RATING_AGAIN || $params['rating'] > sm2::RATING_EASY) {
            throw new invalid_parameter_exception('Rating must be between 1 and 4.');
        }

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/quickcards:use', $context);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        \local_quickcards\manager::require_enabled();

        // Verify card exists and belongs to current user.
        $card = $DB->get_record(
            'local_quickcards_cards',
            ['id' => $params['cardid'], 'userid' => $USER->id],
            '*',
            MUST_EXIST
        );

        $now = time();
        $review = $DB->get_record('local_quickcards_reviews', ['cardid' => $card->id, 'userid' => $USER->id]);

        $currentease = $review ? (float) $review->easefactor : sm2::DEFAULT_EASE_FACTOR;
        $currentinterval = $review ? (int) $review->intervaldays : 0;
        $repetitions = $review ? (int) $review->repetitions : 0;

        $calc = sm2::calculate($currentease, $currentinterval, $repetitions, $params['rating'], $now);

        if ($review) {
            $review->easefactor = $calc['easefactor'];
            $review->intervaldays = $calc['intervaldays'];
            $review->repetitions = $calc['repetitions'];
            $review->nextreview = $calc['nextreview'];
            $review->lastreview = $now;
            $DB->update_record('local_quickcards_reviews', $review);
        } else {
            $review = (object) [
                'cardid' => (int) $card->id,
                'userid' => (int) $USER->id,
                'easefactor' => $calc['easefactor'],
                'intervaldays' => $calc['intervaldays'],
                'repetitions' => $calc['repetitions'],
                'nextreview' => $calc['nextreview'],
                'lastreview' => $now,
            ];
            $DB->insert_record('local_quickcards_reviews', $review);
        }

        return [
            'cardid' => (int) $card->id,
            'rating' => $params['rating'],
            'easefactor' => (float) $calc['easefactor'],
            'intervaldays' => (int) $calc['intervaldays'],
            'repetitions' => (int) $calc['repetitions'],
            'nextreview' => (int) $calc['nextreview'],
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
            'cardid' => new external_value(PARAM_INT, 'Reviewed card ID.'),
            'rating' => new external_value(PARAM_INT, 'Submitted rating.'),
            'easefactor' => new external_value(PARAM_FLOAT, 'Updated ease factor.'),
            'intervaldays' => new external_value(PARAM_INT, 'Updated interval in days.'),
            'repetitions' => new external_value(PARAM_INT, 'Updated repetition count.'),
            'nextreview' => new external_value(PARAM_INT, 'Timestamp of next review.'),
            'success' => new external_value(PARAM_BOOL, 'Whether operation succeeded.'),
        ]);
    }
}
