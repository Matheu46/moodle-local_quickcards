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

namespace local_quickcards;

use moodle_exception;

/**
 * Deck importer class for QuickCards.
 *
 * Imports flashcard decks from JSON payloads into the database,
 * optionally restoring SM-2 spaced repetition progress.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {
    /**
     * Parse and validate a QuickCards JSON payload without saving to the database.
     *
     * @param string $jsoncontent Raw JSON string.
     * @return array Validated structure with deck info and cards count.
     * @throws moodle_exception If JSON or schema is invalid.
     */
    public static function validate_payload(string $jsoncontent): array {
        $data = json_decode($jsoncontent, true);
        if ($data === null || !is_array($data)) {
            throw new moodle_exception('invalidjson', 'local_quickcards');
        }

        if (empty($data['format']) || $data['format'] !== exporter::FORMAT_IDENTIFIER) {
            throw new moodle_exception('invalidfileformat', 'local_quickcards');
        }

        if (empty($data['version']) || version_compare($data['version'], exporter::FORMAT_VERSION, '>')) {
            $versionstr = !empty($data['version']) ? (string) $data['version'] : 'unknown';
            throw new moodle_exception('unsupportedversion', 'local_quickcards', '', $versionstr);
        }

        if (empty($data['deck']) || !is_array($data['deck']) || empty(trim($data['deck']['name'] ?? ''))) {
            throw new moodle_exception('nodeckname', 'local_quickcards');
        }

        if (!isset($data['cards']) || !is_array($data['cards'])) {
            throw new moodle_exception('nofileorcard', 'local_quickcards');
        }

        return $data;
    }

    /**
     * Import a deck and its cards from a JSON payload.
     *
     * @param string $jsoncontent Raw JSON string.
     * @param int $userid Target user ID.
     * @param int $courseid Optional associated course ID.
     * @param bool $restoreprogress Whether to restore SM-2 progress if present in the file.
     * @return array Import summary containing deckid, deckname, cardscount, and progressrestored.
     * @throws moodle_exception If validation or insertion fails.
     */
    public static function import_deck_from_json(
        string $jsoncontent,
        int $userid,
        int $courseid = 0,
        bool $restoreprogress = false
    ): array {
        global $DB;

        $data = self::validate_payload($jsoncontent);

        $deckname = clean_param(trim($data['deck']['name']), PARAM_TEXT);
        $deckdescription = clean_param($data['deck']['description'] ?? '', PARAM_RAW);
        $deckdescformat = (int) ($data['deck']['descriptionformat'] ?? FORMAT_HTML);

        $hasprogress = !empty($data['includesprogress']) && $restoreprogress;
        $now = time();

        $transaction = $DB->start_delegated_transaction();

        // 1. Create the deck record.
        $newdeck = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'name' => $deckname,
            'description' => $deckdescription,
            'descriptionformat' => $deckdescformat,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $deckid = $DB->insert_record('local_quickcards_decks', $newdeck);

        $cardscount = 0;

        // 2. Insert cards and review schedules.
        foreach ($data['cards'] as $carditem) {
            if (!is_array($carditem)) {
                continue;
            }

            $question = clean_param(trim($carditem['question'] ?? ''), PARAM_RAW);
            $answer = clean_param(trim($carditem['answer'] ?? ''), PARAM_RAW);

            if ($question === '' || $answer === '') {
                continue;
            }

            $qformat = (int) ($carditem['questionformat'] ?? FORMAT_HTML);
            $aformat = (int) ($carditem['answerformat'] ?? FORMAT_HTML);

            $cardrecord = (object) [
                'deckid' => $deckid,
                'userid' => $userid,
                'question' => $question,
                'questionformat' => $qformat,
                'answer' => $answer,
                'answerformat' => $aformat,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $cardid = $DB->insert_record('local_quickcards_cards', $cardrecord);

            // Review schedule.
            if ($hasprogress && !empty($carditem['progress']) && is_array($carditem['progress'])) {
                $prog = $carditem['progress'];
                $ease = (float) ($prog['easefactor'] ?? sm2::DEFAULT_EASE_FACTOR);
                if ($ease < sm2::MINIMUM_EASE_FACTOR) {
                    $ease = sm2::MINIMUM_EASE_FACTOR;
                }
                $reviewrecord = (object) [
                    'cardid' => $cardid,
                    'userid' => $userid,
                    'easefactor' => $ease,
                    'intervaldays' => max(0, (int) ($prog['intervaldays'] ?? 0)),
                    'repetitions' => max(0, (int) ($prog['repetitions'] ?? 0)),
                    'nextreview' => !empty($prog['nextreview']) ? (int) $prog['nextreview'] : $now,
                    'lastreview' => !empty($prog['lastreview']) ? (int) $prog['lastreview'] : null,
                ];
            } else {
                $reviewrecord = (object) [
                    'cardid' => $cardid,
                    'userid' => $userid,
                    'easefactor' => sm2::DEFAULT_EASE_FACTOR,
                    'intervaldays' => 0,
                    'repetitions' => 0,
                    'nextreview' => $now,
                    'lastreview' => null,
                ];
            }
            $DB->insert_record('local_quickcards_reviews', $reviewrecord);
            $cardscount++;
        }

        $transaction->allow_commit();

        return [
            'deckid' => (int) $deckid,
            'deckname' => $deckname,
            'cardscount' => $cardscount,
            'progressrestored' => $hasprogress,
        ];
    }
}
