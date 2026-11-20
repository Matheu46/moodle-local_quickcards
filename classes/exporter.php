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

/**
 * Deck exporter class for QuickCards.
 *
 * Exports a user's flashcard deck and cards to a structured JSON format,
 * optionally including the user's SM-2 spaced repetition progress.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /** @var string Export schema format identifier. */
    public const FORMAT_IDENTIFIER = 'local_quickcards';

    /** @var string Export schema version. */
    public const FORMAT_VERSION = '1.0';

    /**
     * Export a deck and its cards as a structured associative array.
     *
     * @param int $deckid Deck ID to export.
     * @param int $userid Owner user ID.
     * @param bool $includeprogress Whether to include SM-2 study progress for each card.
     * @return array Export payload.
     */
    public static function export_deck(int $deckid, int $userid, bool $includeprogress = false): array {
        global $DB;

        $deck = $DB->get_record(
            'local_quickcards_decks',
            ['id' => $deckid, 'userid' => $userid],
            '*',
            MUST_EXIST
        );

        $sql = "SELECT c.id,
                       c.question,
                       c.questionformat,
                       c.answer,
                       c.answerformat,
                       r.easefactor,
                       r.intervaldays,
                       r.repetitions,
                       r.nextreview,
                       r.lastreview
                  FROM {local_quickcards_cards} c
             LEFT JOIN {local_quickcards_reviews} r ON r.cardid = c.id AND r.userid = :ruserid
                 WHERE c.deckid = :deckid AND c.userid = :cuserid
              ORDER BY c.id ASC";

        $records = $DB->get_records_sql($sql, [
            'ruserid' => $userid,
            'deckid' => $deck->id,
            'cuserid' => $userid,
        ]);

        $now = time();
        $cards = [];

        foreach ($records as $record) {
            $carddata = [
                'question' => $record->question,
                'questionformat' => (int) ($record->questionformat ?? FORMAT_HTML),
                'answer' => $record->answer,
                'answerformat' => (int) ($record->answerformat ?? FORMAT_HTML),
            ];

            if ($includeprogress) {
                $carddata['progress'] = [
                    'easefactor' => (float) ($record->easefactor ?? sm2::DEFAULT_EASE_FACTOR),
                    'intervaldays' => (int) ($record->intervaldays ?? 0),
                    'repetitions' => (int) ($record->repetitions ?? 0),
                    'nextreview' => !empty($record->nextreview) ? (int) $record->nextreview : $now,
                    'lastreview' => !empty($record->lastreview) ? (int) $record->lastreview : null,
                ];
            }

            $cards[] = $carddata;
        }

        return [
            'format' => self::FORMAT_IDENTIFIER,
            'version' => self::FORMAT_VERSION,
            'exportedat' => $now,
            'includesprogress' => $includeprogress,
            'deck' => [
                'name' => $deck->name,
                'description' => $deck->description ?? '',
                'descriptionformat' => (int) ($deck->descriptionformat ?? FORMAT_HTML),
            ],
            'cards' => $cards,
        ];
    }

    /**
     * Export a deck as a formatted JSON string.
     *
     * @param int $deckid Deck ID to export.
     * @param int $userid Owner user ID.
     * @param bool $includeprogress Whether to include SM-2 study progress.
     * @return string JSON string.
     */
    public static function export_deck_json(int $deckid, int $userid, bool $includeprogress = false): string {
        $payload = self::export_deck($deckid, $userid, $includeprogress);
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a safe download filename for an exported deck.
     *
     * @param string $deckname Raw deck name.
     * @param bool $includeprogress Whether progress is included.
     * @return string Sanitized filename ending in .json.
     */
    public static function generate_filename(string $deckname, bool $includeprogress = false): string {
        $clean = clean_filename($deckname);
        $clean = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $clean);
        $clean = trim(strtolower((string) $clean), '-');

        if ($clean === '') {
            $clean = 'deck';
        }

        $suffix = $includeprogress ? '-backup' : '';
        $date = date('Ymd');

        return 'quickcards-' . $clean . $suffix . '-' . $date . '.json';
    }
}
