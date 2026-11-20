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

    /**
     * Detect the best delimiter from a CSV/TSV text snippet.
     *
     * @param string $content Normalized CSV/TSV text.
     * @return string Single-character delimiter (\t, ;, or ,).
     */
    public static function detect_delimiter(string $content): string {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (strpos($trimmed, "\t") !== false) {
                return "\t";
            }
            if (strpos($trimmed, ';') !== false) {
                return ';';
            }
            return ',';
        }
        return ',';
    }

    /**
     * Parse cards from CSV or tab-separated text (Anki / Quizlet / Excel).
     *
     * @param string $csvcontent Raw CSV/TSV text content.
     * @param string $delimiter Delimiter character or 'auto' for auto-detection.
     * @return array List of ['question' => string, 'answer' => string].
     */
    public static function parse_csv_cards(string $csvcontent, string $delimiter = 'auto'): array {
        // Strip UTF-8 BOM if present and normalize line endings.
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $csvcontent);
        $normalized = str_replace(["\r\n", "\r"], "\n", (string) $normalized);

        if ($delimiter === 'auto' || $delimiter === '') {
            $delimiter = self::detect_delimiter($normalized);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $normalized);
        rewind($handle);

        $cards = [];
        $isfirstrow = true;
        $headerquestions = ['question', 'front', 'term', 'pergunta', 'frente', 'termo'];
        $headeranswers = ['answer', 'back', 'definition', 'resposta', 'verso', 'definicao', 'definição'];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $question = clean_param(trim((string) $row[0]), PARAM_RAW);
            $answer = clean_param(trim((string) $row[1]), PARAM_RAW);

            if ($question === '' || $answer === '') {
                continue;
            }

            // Skip standard header row if detected on the first non-empty row.
            if ($isfirstrow) {
                $isfirstrow = false;
                $qlower = \core_text::strtolower($question);
                $alower = \core_text::strtolower($answer);
                if (in_array($qlower, $headerquestions, true) && in_array($alower, $headeranswers, true)) {
                    continue;
                }
            }

            $cards[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        fclose($handle);
        return $cards;
    }

    /**
     * Import a new deck from CSV or tab-separated text (Anki / Quizlet / Excel).
     *
     * @param string $deckname Name for the new deck.
     * @param string $csvcontent Raw CSV/TSV text content.
     * @param int $userid Target user ID.
     * @param int $courseid Optional associated course ID.
     * @param string $delimiter Delimiter character or 'auto'.
     * @param string $description Optional deck description.
     * @return array Import summary containing deckid, deckname, and cardscount.
     * @throws moodle_exception If deck name is empty or no valid cards are found.
     */
    public static function import_deck_from_csv(
        string $deckname,
        string $csvcontent,
        int $userid,
        int $courseid = 0,
        string $delimiter = 'auto',
        string $description = ''
    ): array {
        global $DB;

        $cleandeckname = clean_param(trim($deckname), PARAM_TEXT);
        if ($cleandeckname === '') {
            throw new moodle_exception('nodeckname', 'local_quickcards');
        }

        $cards = self::parse_csv_cards($csvcontent, $delimiter);
        if (empty($cards)) {
            throw new moodle_exception('nofileorcard', 'local_quickcards');
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $newdeck = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'name' => $cleandeckname,
            'description' => clean_param($description, PARAM_RAW),
            'descriptionformat' => FORMAT_HTML,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $deckid = $DB->insert_record('local_quickcards_decks', $newdeck);

        foreach ($cards as $carditem) {
            $cardrecord = (object) [
                'deckid' => $deckid,
                'userid' => $userid,
                'question' => $carditem['question'],
                'questionformat' => FORMAT_HTML,
                'answer' => $carditem['answer'],
                'answerformat' => FORMAT_HTML,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $cardid = $DB->insert_record('local_quickcards_cards', $cardrecord);

            $reviewrecord = (object) [
                'cardid' => $cardid,
                'userid' => $userid,
                'easefactor' => sm2::DEFAULT_EASE_FACTOR,
                'intervaldays' => 0,
                'repetitions' => 0,
                'nextreview' => $now,
                'lastreview' => null,
            ];
            $DB->insert_record('local_quickcards_reviews', $reviewrecord);
        }

        $transaction->allow_commit();

        return [
            'deckid' => (int) $deckid,
            'deckname' => $cleandeckname,
            'cardscount' => count($cards),
        ];
    }
}
