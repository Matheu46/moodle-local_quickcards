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

namespace local_quickcards\privacy;

use context;
use context_course;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_quickcards.
 *
 * @package     local_quickcards
 * @category    privacy
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return metadata about user data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_quickcards_decks',
            [
                'userid' => 'privacy:metadata:local_quickcards_decks:userid',
                'courseid' => 'privacy:metadata:local_quickcards_decks:courseid',
                'name' => 'privacy:metadata:local_quickcards_decks:name',
                'description' => 'privacy:metadata:local_quickcards_decks:description',
                'timecreated' => 'privacy:metadata:local_quickcards_decks:timecreated',
                'timemodified' => 'privacy:metadata:local_quickcards_decks:timemodified',
            ],
            'privacy:metadata:local_quickcards_decks'
        );

        $collection->add_database_table(
            'local_quickcards_cards',
            [
                'deckid' => 'privacy:metadata:local_quickcards_cards:deckid',
                'userid' => 'privacy:metadata:local_quickcards_cards:userid',
                'question' => 'privacy:metadata:local_quickcards_cards:question',
                'answer' => 'privacy:metadata:local_quickcards_cards:answer',
                'timecreated' => 'privacy:metadata:local_quickcards_cards:timecreated',
                'timemodified' => 'privacy:metadata:local_quickcards_cards:timemodified',
            ],
            'privacy:metadata:local_quickcards_cards'
        );

        $collection->add_database_table(
            'local_quickcards_reviews',
            [
                'cardid' => 'privacy:metadata:local_quickcards_reviews:cardid',
                'userid' => 'privacy:metadata:local_quickcards_reviews:userid',
                'easefactor' => 'privacy:metadata:local_quickcards_reviews:easefactor',
                'intervaldays' => 'privacy:metadata:local_quickcards_reviews:intervaldays',
                'repetitions' => 'privacy:metadata:local_quickcards_reviews:repetitions',
                'nextreview' => 'privacy:metadata:local_quickcards_reviews:nextreview',
                'lastreview' => 'privacy:metadata:local_quickcards_reviews:lastreview',
            ],
            'privacy:metadata:local_quickcards_reviews'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        // 1. User context: for general decks (courseid = 0), standalone cards or reviews.
        $hasgeneraldata = $DB->record_exists('local_quickcards_decks', ['userid' => $userid, 'courseid' => 0])
            || $DB->record_exists('local_quickcards_cards', ['userid' => $userid])
            || $DB->record_exists('local_quickcards_reviews', ['userid' => $userid]);

        if ($hasgeneraldata) {
            $contextlist->add_user_context($userid);
        }

        // 2. Course contexts: for decks linked to specific courses (courseid > 0).
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                  JOIN {local_quickcards_decks} d ON d.courseid = c.instanceid AND d.courseid > 0
                 WHERE c.contextlevel = :contextlevel
                   AND d.userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_USER) {
            $userid = (int) $context->instanceid;
            $sql = "SELECT u.id
                      FROM {user} u
                     WHERE u.id = :userid
                       AND (
                           EXISTS (SELECT 1 FROM {local_quickcards_decks} d WHERE d.userid = u.id AND d.courseid = 0)
                        OR EXISTS (SELECT 1 FROM {local_quickcards_cards} cd WHERE cd.userid = u.id)
                        OR EXISTS (SELECT 1 FROM {local_quickcards_reviews} r WHERE r.userid = u.id)
                       )";
            $userlist->add_from_sql('id', $sql, ['userid' => $userid]);
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = (int) $context->instanceid;
            $sql = "SELECT DISTINCT d.userid
                      FROM {local_quickcards_decks} d
                     WHERE d.courseid = :courseid";
            $userlist->add_from_sql('userid', $sql, ['courseid' => $courseid]);
        }
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $userid = (int) $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            $decks = [];
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                $decks = $DB->get_records('local_quickcards_decks', ['userid' => $userid, 'courseid' => 0], 'name ASC');
            } else if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = (int) $context->instanceid;
                $decks = $DB->get_records(
                    'local_quickcards_decks',
                    ['userid' => $userid, 'courseid' => $courseid],
                    'name ASC'
                );
            }

            if (empty($decks)) {
                continue;
            }

            foreach ($decks as $deck) {
                $deckdata = (object) [
                    'name' => format_string($deck->name),
                    'description' => format_text($deck->description ?? '', FORMAT_PLAIN),
                    'timecreated' => transform::datetime($deck->timecreated),
                    'timemodified' => transform::datetime($deck->timemodified),
                    'cards' => [],
                ];

                $cards = $DB->get_records('local_quickcards_cards', ['deckid' => $deck->id], 'id ASC');
                foreach ($cards as $card) {
                    $carddata = [
                        'question' => format_text($card->question, $card->questionformat),
                        'answer' => format_text($card->answer, $card->answerformat),
                        'timecreated' => transform::datetime($card->timecreated),
                        'timemodified' => transform::datetime($card->timemodified),
                    ];

                    $review = $DB->get_record('local_quickcards_reviews', ['cardid' => $card->id, 'userid' => $userid]);
                    if ($review) {
                        $carddata['study_progress'] = [
                            'easefactor' => (float) $review->easefactor,
                            'intervaldays' => (int) $review->intervaldays,
                            'repetitions' => (int) $review->repetitions,
                            'nextreview' => transform::datetime($review->nextreview),
                            'lastreview' => $review->lastreview ? transform::datetime($review->lastreview) : null,
                        ];
                    }

                    $deckdata->cards[] = $carddata;
                }

                $subcontext = [
                    get_string('pluginname', 'local_quickcards'),
                    format_string($deck->name),
                ];
                writer::with_context($context)->export_data($subcontext, $deckdata);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel == CONTEXT_USER) {
            $userid = (int) $context->instanceid;
            self::delete_all_user_data_for_user($userid);
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = (int) $context->instanceid;
            $deckids = $DB->get_fieldset_select(
                'local_quickcards_decks',
                'id',
                'courseid = :courseid',
                ['courseid' => $courseid]
            );
            foreach ($deckids as $deckid) {
                self::delete_deck_data((int) $deckid);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $userid = (int) $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                self::delete_all_user_data_for_user($userid);
            } else if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = (int) $context->instanceid;
                $deckids = $DB->get_fieldset_select(
                    'local_quickcards_decks',
                    'id',
                    'userid = :userid AND courseid = :courseid',
                    ['userid' => $userid, 'courseid' => $courseid]
                );
                foreach ($deckids as $deckid) {
                    self::delete_deck_data((int) $deckid);
                }
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        if ($context->contextlevel == CONTEXT_USER) {
            foreach ($userids as $userid) {
                if ((int) $context->instanceid === (int) $userid) {
                    self::delete_all_user_data_for_user((int) $userid);
                }
            }
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = (int) $context->instanceid;
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;

            $deckids = $DB->get_fieldset_select(
                'local_quickcards_decks',
                'id',
                "courseid = :courseid AND userid {$insql}",
                $params
            );
            foreach ($deckids as $deckid) {
                self::delete_deck_data((int) $deckid);
            }
        }
    }

    /**
     * Delete all general decks, cards, and review records belonging to a user.
     *
     * @param int $userid The user ID.
     */
    protected static function delete_all_user_data_for_user(int $userid): void {
        global $DB;

        $deckids = $DB->get_fieldset_select(
            'local_quickcards_decks',
            'id',
            'userid = :userid AND courseid = 0',
            ['userid' => $userid]
        );
        foreach ($deckids as $deckid) {
            self::delete_deck_data((int) $deckid);
        }

        // Clean up any remaining standalone reviews or cards belonging to the user.
        $DB->delete_records('local_quickcards_reviews', ['userid' => $userid]);
        $DB->delete_records('local_quickcards_cards', ['userid' => $userid]);
    }

    /**
     * Delete a deck and all associated cards and reviews.
     *
     * @param int $deckid The deck ID.
     */
    protected static function delete_deck_data(int $deckid): void {
        global $DB;

        $cardids = $DB->get_fieldset_select(
            'local_quickcards_cards',
            'id',
            'deckid = :deckid',
            ['deckid' => $deckid]
        );
        if (!empty($cardids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_quickcards_reviews', "cardid $insql", $inparams);
        }
        $DB->delete_records('local_quickcards_cards', ['deckid' => $deckid]);
        $DB->delete_records('local_quickcards_decks', ['id' => $deckid]);
    }
}
