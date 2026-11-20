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

use stdClass;

/**
 * Data management helper for QuickCards decks, cards, and study statistics.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Check whether QuickCards is enabled in site settings.
     *
     * @return bool True if enabled.
     */
    public static function is_enabled(): bool {
        $enabled = get_config('local_quickcards', 'enabled');
        if ($enabled === false) {
            return true;
        }
        return !empty($enabled);
    }

    /**
     * Require that QuickCards is enabled globally, throwing an exception otherwise.
     *
     * @throws \moodle_exception
     */
    public static function require_enabled(): void {
        if (!self::is_enabled()) {
            throw new \moodle_exception('plugindisabled', 'local_quickcards');
        }
    }

    /**
     * Return the list of configured allowed cohort IDs.
     *
     * An empty array means no cohort restriction is active (all permitted users may access).
     *
     * @return int[] Array of cohort IDs.
     */
    public static function get_allowed_cohort_ids(): array {
        $raw = get_config('local_quickcards', 'allowedcohorts');
        if ($raw === false || trim((string) $raw) === '') {
            return [];
        }

        $ids = array_filter(array_map('intval', explode(',', (string) $raw)), static function (int $id): bool {
            return $id > 0;
        });

        return array_values(array_unique($ids));
    }

    /**
     * Check whether a user satisfies the cohort access restriction.
     *
     * Site administrators always bypass cohort restrictions so they can configure and test the plugin.
     *
     * @param int|null $userid User ID (defaults to current user).
     * @return bool True if no cohort restriction is active, user is site admin, or user belongs to an allowed cohort.
     */
    public static function user_in_allowed_cohorts(?int $userid = null): bool {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }

        $cohortids = self::get_allowed_cohort_ids();
        if (empty($cohortids)) {
            return true;
        }

        if (is_siteadmin($userid)) {
            return true;
        }

        [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;

        return $DB->record_exists_select('cohort_members', "userid = :userid AND cohortid {$insql}", $params);
    }

    /**
     * Check whether a user can access QuickCards (plugin enabled, capability granted, and cohort restriction satisfied).
     *
     * @param int|null $userid User ID (defaults to current user).
     * @return bool True if the user can access QuickCards.
     */
    public static function can_access(?int $userid = null): bool {
        global $USER;

        if (!self::is_enabled()) {
            return false;
        }

        $userid = $userid ?? (int) $USER->id;
        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }

        if (!has_capability('local/quickcards:use', \context_system::instance(), $userid)) {
            return false;
        }

        return self::user_in_allowed_cohorts($userid);
    }

    /**
     * Require that QuickCards is enabled and the user satisfies cohort restrictions.
     *
     * @param int|null $userid User ID (defaults to current user).
     * @throws \moodle_exception
     */
    public static function require_access(?int $userid = null): void {
        global $USER;

        self::require_enabled();

        $userid = $userid ?? (int) $USER->id;
        if ($userid <= 0 || isguestuser($userid)) {
            throw new \moodle_exception('noguest');
        }

        if (!self::user_in_allowed_cohorts($userid)) {
            throw new \moodle_exception('accessrestricted', 'local_quickcards');
        }
    }
    /**
     * Get all decks created by a user, enriched with card counts and due reviews.
     *
     * @param int $userid User ID.
     * @return array Array of deck objects.
     */
    public static function get_user_decks(int $userid): array {
        global $DB;

        $now = time();

        $sql = "SELECT d.*,
                       c.fullname AS coursename,
                       COUNT(DISTINCT card.id) AS totalcards,
                       COUNT(DISTINCT CASE WHEN r.nextreview <= :now THEN card.id ELSE NULL END) AS duecount
                  FROM {local_quickcards_decks} d
             LEFT JOIN {course} c ON c.id = d.courseid
             LEFT JOIN {local_quickcards_cards} card ON card.deckid = d.id
             LEFT JOIN {local_quickcards_reviews} r ON r.cardid = card.id AND r.userid = :ruserid
                 WHERE d.userid = :duserid
              GROUP BY d.id, c.fullname
              ORDER BY d.timemodified DESC";

        $records = $DB->get_records_sql($sql, [
            'now' => $now,
            'ruserid' => $userid,
            'duserid' => $userid,
        ]);

        $context = \context_system::instance();
        $decks = [];
        foreach ($records as $rec) {
            $deckurl = new \moodle_url('/local/quickcards/deck.php', ['id' => $rec->id]);
            $studyurl = new \moodle_url('/local/quickcards/study.php', ['deckid' => $rec->id]);

            $decks[] = [
                'id' => (int) $rec->id,
                'name' => format_string($rec->name, true, ['context' => $context]),
                'rawname' => $rec->name,
                'description' => format_text($rec->description ?? '', FORMAT_HTML, ['context' => $context]),
                'rawdescription' => $rec->description ?? '',
                'courseid' => (int) $rec->courseid,
                'coursename' => !empty($rec->coursename) ? format_string($rec->coursename, true, ['context' => $context]) : '',
                'hascourse' => !empty($rec->courseid) && !empty($rec->coursename),
                'totalcards' => (int) $rec->totalcards,
                'duecount' => (int) $rec->duecount,
                'hasdue' => ((int) $rec->duecount) > 0,
                'hascards' => ((int) $rec->totalcards) > 0,
                'deckurl' => $deckurl->out(false),
                'studyurl' => $studyurl->out(false),
            ];
        }

        return $decks;
    }

    /**
     * Get aggregate statistics for a user.
     *
     * @param int $userid User ID.
     * @return array
     */
    public static function get_user_stats(int $userid): array {
        global $DB;

        $now = time();

        $totaldecks = $DB->count_records('local_quickcards_decks', ['userid' => $userid]);
        $totalcards = $DB->count_records('local_quickcards_cards', ['userid' => $userid]);

        $sql = "SELECT COUNT(r.id)
                  FROM {local_quickcards_reviews} r
                  JOIN {local_quickcards_cards} c ON c.id = r.cardid
                 WHERE r.userid = :userid AND r.nextreview <= :now";
        $totaldue = (int) $DB->count_records_sql($sql, ['userid' => $userid, 'now' => $now]);

        return [
            'totaldecks' => $totaldecks,
            'totalcards' => $totalcards,
            'totaldue' => $totaldue,
            'hasdue' => $totaldue > 0,
        ];
    }

    /**
     * Get a specific deck and its cards.
     *
     * @param int $deckid Deck ID.
     * @param int $userid User ID.
     * @return array|null Deck details or null if not found.
     */
    public static function get_deck_with_cards(int $deckid, int $userid): ?array {
        global $DB;

        $deck = $DB->get_record('local_quickcards_decks', ['id' => $deckid, 'userid' => $userid]);
        if (!$deck) {
            return null;
        }

        $now = time();
        $sql = "SELECT c.*,
                       r.easefactor,
                       r.intervaldays,
                       r.repetitions,
                       r.nextreview,
                       r.lastreview
                  FROM {local_quickcards_cards} c
             LEFT JOIN {local_quickcards_reviews} r ON r.cardid = c.id AND r.userid = :userid
                 WHERE c.deckid = :deckid AND c.userid = :carduserid
              ORDER BY c.id DESC";

        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'deckid' => $deckid,
            'carduserid' => $userid,
        ]);

        $context = \context_system::instance();
        $cards = [];
        $duecount = 0;
        $newcount = 0;
        $learnedcount = 0;
        foreach ($records as $card) {
            $isdue = (!empty($card->nextreview) && (int) $card->nextreview <= $now);
            $isnew = (empty($card->lastreview) || (int) ($card->repetitions ?? 0) === 0);
            $islearned = (!$isnew && !$isdue);
            if ($isdue) {
                $duecount++;
            }
            if ($isnew) {
                $newcount++;
            }
            if ($islearned) {
                $learnedcount++;
            }

            $cards[] = [
                'id' => (int) $card->id,
                'deckid' => (int) $card->deckid,
                'question' => format_text($card->question, $card->questionformat ?? FORMAT_HTML, ['context' => $context]),
                'rawquestion' => $card->question,
                'answer' => format_text($card->answer, $card->answerformat ?? FORMAT_HTML, ['context' => $context]),
                'rawanswer' => $card->answer,
                'isdue' => $isdue,
                'isnew' => $isnew,
                'islearned' => $islearned,
                'intervaldays' => (int) ($card->intervaldays ?? 0),
                'repetitions' => (int) ($card->repetitions ?? 0),
                'nextreviewhuman' => self::format_next_review((int) ($card->nextreview ?? 0), (int) ($card->lastreview ?? 0)),
            ];
        }

        $coursename = '';
        if (!empty($deck->courseid)) {
            $course = $DB->get_record('course', ['id' => $deck->courseid], 'id, fullname');
            if ($course) {
                $coursename = format_string($course->fullname, true, ['context' => $context]);
            }
        }

        $studyurl = new \moodle_url('/local/quickcards/study.php', ['deckid' => $deck->id]);

        return [
            'id' => (int) $deck->id,
            'name' => format_string($deck->name, true, ['context' => $context]),
            'rawname' => $deck->name,
            'description' => format_text($deck->description ?? '', FORMAT_HTML, ['context' => $context]),
            'rawdescription' => $deck->description ?? '',
            'courseid' => (int) $deck->courseid,
            'coursename' => $coursename,
            'hascourse' => !empty($coursename),
            'totalcards' => count($cards),
            'duecount' => $duecount,
            'newcount' => $newcount,
            'learnedcount' => $learnedcount,
            'hasdue' => $duecount > 0,
            'hasnew' => $newcount > 0,
            'haslearned' => $learnedcount > 0,
            'hascards' => count($cards) > 0,
            'studyurl' => $studyurl->out(false),
            'cards' => $cards,
        ];
    }

    /**
     * Get enrolled courses for current user (for deck association select).
     *
     * @param int $userid User ID.
     * @return array
     */
    public static function get_user_courses_options(int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/lib/enrollib.php');

        $context = \context_system::instance();
        $courses = enrol_get_all_users_courses($userid, true, 'id, fullname, shortname');
        $options = [];
        foreach ($courses as $c) {
            if ((int) $c->id === (int) SITEID) {
                continue;
            }
            $options[] = [
                'id' => (int) $c->id,
                'fullname' => format_string($c->fullname, true, ['context' => $context]),
            ];
        }

        return $options;
    }

    /**
     * Get cards due for review for study session.
     *
     * @param int $userid User ID.
     * @param int $deckid Deck ID (0 for all decks).
     * @return array
     */
    public static function get_due_cards_for_study(int $userid, int $deckid = 0): array {
        global $DB;

        $now = time();
        $context = \context_system::instance();

        $where = "c.userid = :userid AND (r.nextreview IS NULL OR r.nextreview <= :now)";
        $params = [
            'userid' => $userid,
            'now' => $now,
            'ruserid' => $userid,
        ];

        if ($deckid > 0) {
            $where .= " AND c.deckid = :deckid";
            $params['deckid'] = $deckid;
        }

        $sql = "SELECT c.*,
                       d.name AS deckname,
                       r.easefactor,
                       r.intervaldays,
                       r.repetitions,
                       r.nextreview
                  FROM {local_quickcards_cards} c
                  JOIN {local_quickcards_decks} d ON d.id = c.deckid
             LEFT JOIN {local_quickcards_reviews} r ON r.cardid = c.id AND r.userid = :ruserid
                 WHERE $where
              ORDER BY COALESCE(r.nextreview, 0) ASC, c.id ASC";

        $records = $DB->get_records_sql($sql, $params);

        $duecards = [];
        foreach ($records as $card) {
            $duecards[] = [
                'id' => (int) $card->id,
                'deckid' => (int) $card->deckid,
                'deckname' => format_string($card->deckname, true, ['context' => $context]),
                'question' => format_text($card->question, $card->questionformat ?? FORMAT_HTML, ['context' => $context]),
                'answer' => format_text($card->answer, $card->answerformat ?? FORMAT_HTML, ['context' => $context]),
                'easefactor' => (float) ($card->easefactor ?? sm2::DEFAULT_EASE_FACTOR),
                'intervaldays' => (int) ($card->intervaldays ?? 0),
                'repetitions' => (int) ($card->repetitions ?? 0),
            ];
        }

        return $duecards;
    }

    /**
     * Get other decks owned by user (excluding current deck).
     *
     * @param int $userid User ID.
     * @param int $currentdeckid Current Deck ID to exclude.
     * @return array
     */
    public static function get_user_other_decks(int $userid, int $currentdeckid): array {
        global $DB;

        $context = \context_system::instance();
        $records = $DB->get_records_select(
            'local_quickcards_decks',
            'userid = :userid AND id != :deckid',
            ['userid' => $userid, 'deckid' => $currentdeckid],
            'name ASC',
            'id, name'
        );

        $decks = [];
        foreach ($records as $r) {
            $decks[] = [
                'id' => (int) $r->id,
                'name' => format_string($r->name, true, ['context' => $context]),
            ];
        }

        return $decks;
    }

    /**
     * Helper to format a human-readable next review string.
     *
     * @param int $nextreview Timestamp.
     * @param int $lastreview Timestamp.
     * @return string
     */
    private static function format_next_review(int $nextreview, int $lastreview): string {
        if ($lastreview === 0 && $nextreview === 0) {
            return get_string('never', 'local_quickcards');
        }

        $now = time();
        if ($nextreview <= $now) {
            return get_string('today', 'local_quickcards');
        }

        $diffdays = (int) ceil(($nextreview - $now) / DAYSECS);
        return get_string('inxdays', 'local_quickcards', $diffdays);
    }
}
