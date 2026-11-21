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

use context_course;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Unit tests for Privacy API provider in local_quickcards.
 *
 * @package     local_quickcards
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_quickcards\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Test get_metadata adds all 3 database tables and has all language strings.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_quickcards');
        $newcollection = provider::get_metadata($collection);

        $items = $newcollection->get_collection();
        $this->assertCount(3, $items);

        $tables = [];
        foreach ($items as $item) {
            $tables[] = $item->get_name();
        }
        $this->assertContains('local_quickcards_decks', $tables);
        $this->assertContains('local_quickcards_cards', $tables);
        $this->assertContains('local_quickcards_reviews', $tables);
    }

    /**
     * Test get_contexts_for_userid returns user context for general decks and course context for course decks.
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Initially no contexts.
        $contextlist = provider::get_contexts_for_userid((int) $user1->id);
        $this->assertEmpty($contextlist->get_contextids());

        // Create general deck for user1.
        $now = time();
        $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user1->id,
            'courseid' => 0,
            'name' => 'General Deck',
            'description' => 'Desc',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Create course deck for user1.
        $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user1->id,
            'courseid' => (int) $course->id,
            'name' => 'Course Deck',
            'description' => 'Desc',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $user1context = context_user::instance($user1->id);
        $coursecontext = context_course::instance($course->id);

        $contextlist = provider::get_contexts_for_userid((int) $user1->id);
        $this->assertCount(2, $contextlist->get_contextids());
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) $user1context->id, $contextids);
        $this->assertContains((int) $coursecontext->id, $contextids);

        // The user2 still has no contexts.
        $contextlist2 = provider::get_contexts_for_userid((int) $user2->id);
        $this->assertEmpty($contextlist2->get_contextids());
    }

    /**
     * Test get_users_in_context identifies users in user context and course context.
     */
    public function test_get_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $usercontext = context_user::instance($user->id);
        $coursecontext = context_course::instance($course->id);

        // Before data creation, userlist is empty.
        $userlist = new userlist($usercontext, 'local_quickcards');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());

        // Create decks.
        $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user->id,
            'courseid' => 0,
            'name' => 'General Deck',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user->id,
            'courseid' => (int) $course->id,
            'name' => 'Course Deck',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // User context finds user.
        $userlist = new userlist($usercontext, 'local_quickcards');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $user->id], $userlist->get_userids());

        // Course context finds user.
        $courseuserlist = new userlist($coursecontext, 'local_quickcards');
        provider::get_users_in_context($courseuserlist);
        $this->assertEquals([(int) $user->id], $courseuserlist->get_userids());
    }

    /**
     * Test export_user_data exports deck and card data into the writer.
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);
        $now = time();

        $deckid = $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user->id,
            'courseid' => 0,
            'name' => 'Biology',
            'description' => 'Bio deck',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $cardid = $DB->insert_record('local_quickcards_cards', (object) [
            'deckid' => $deckid,
            'userid' => (int) $user->id,
            'question' => 'What is ATP?',
            'questionformat' => FORMAT_PLAIN,
            'answer' => 'Energy currency',
            'answerformat' => FORMAT_PLAIN,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('local_quickcards_reviews', (object) [
            'cardid' => $cardid,
            'userid' => (int) $user->id,
            'easefactor' => 2.50,
            'intervaldays' => 1,
            'repetitions' => 1,
            'nextreview' => $now + 86400,
            'lastreview' => $now,
        ]);

        $approvedlist = new approved_contextlist($user, 'local_quickcards', [$usercontext->id]);
        provider::export_user_data($approvedlist);

        $writer = writer::with_context($usercontext);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data([get_string('pluginname', 'local_quickcards'), 'Biology']);
        $this->assertNotEmpty($data);
        $this->assertEquals('Biology', $data->name);
        $this->assertCount(1, $data->cards);
        $this->assertEquals('What is ATP?', $data->cards[0]['question']);
        $this->assertEquals('Energy currency', $data->cards[0]['answer']);
        $this->assertNotEmpty($data->cards[0]['study_progress']);
        $this->assertEquals(2.50, $data->cards[0]['study_progress']['easefactor']);
    }

    /**
     * Test delete_data_for_user removes user's decks, cards, and reviews in approved contexts.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $now = time();

        $deck1 = $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user1->id,
            'courseid' => 0,
            'name' => 'User1 Deck',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $card1 = $DB->insert_record('local_quickcards_cards', (object) [
            'deckid' => $deck1,
            'userid' => (int) $user1->id,
            'question' => 'Q1',
            'answer' => 'A1',
            'questionformat' => 1,
            'answerformat' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_quickcards_reviews', (object) [
            'cardid' => $card1,
            'userid' => (int) $user1->id,
            'easefactor' => 2.50,
            'intervaldays' => 1,
            'repetitions' => 1,
            'nextreview' => $now,
        ]);

        // Create user 2 deck.
        $deck2 = $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user2->id,
            'courseid' => 0,
            'name' => 'User2 Deck',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $user1context = context_user::instance($user1->id);
        $approvedlist = new approved_contextlist($user1, 'local_quickcards', [$user1context->id]);
        provider::delete_data_for_user($approvedlist);

        // User1 data deleted.
        $this->assertFalse($DB->record_exists('local_quickcards_decks', ['id' => $deck1]));
        $this->assertFalse($DB->record_exists('local_quickcards_cards', ['id' => $card1]));
        $this->assertFalse($DB->record_exists('local_quickcards_reviews', ['userid' => $user1->id]));

        // User2 data intact.
        $this->assertTrue($DB->record_exists('local_quickcards_decks', ['id' => $deck2]));
    }

    /**
     * Test delete_data_for_all_users_in_context deletes all decks in a course context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $deck = $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user->id,
            'courseid' => (int) $course->id,
            'name' => 'Course Deck',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $card = $DB->insert_record('local_quickcards_cards', (object) [
            'deckid' => $deck,
            'userid' => (int) $user->id,
            'question' => 'Q',
            'answer' => 'A',
            'questionformat' => 1,
            'answerformat' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $coursecontext = context_course::instance($course->id);
        provider::delete_data_for_all_users_in_context($coursecontext);

        $this->assertFalse($DB->record_exists('local_quickcards_decks', ['id' => $deck]));
        $this->assertFalse($DB->record_exists('local_quickcards_cards', ['id' => $card]));
    }

    /**
     * Test delete_data_for_users deletes approved users in course context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $deck1 = $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user1->id,
            'courseid' => (int) $course->id,
            'name' => 'Course Deck 1',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $deck2 = $DB->insert_record('local_quickcards_decks', (object) [
            'userid' => (int) $user2->id,
            'courseid' => (int) $course->id,
            'name' => 'Course Deck 2',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $coursecontext = context_course::instance($course->id);
        $approveduserlist = new approved_userlist($coursecontext, 'local_quickcards', [(int) $user1->id]);
        provider::delete_data_for_users($approveduserlist);

        $this->assertFalse($DB->record_exists('local_quickcards_decks', ['id' => $deck1]));
        $this->assertTrue($DB->record_exists('local_quickcards_decks', ['id' => $deck2]));
    }
}
