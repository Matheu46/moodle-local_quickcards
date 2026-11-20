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

use advanced_testcase;
use core_external\external_api;
use dml_missing_record_exception;
use invalid_parameter_exception;
use local_quickcards\external\create_card;
use local_quickcards\external\create_deck;
use local_quickcards\external\delete_card;
use local_quickcards\external\delete_deck;
use local_quickcards\external\move_card;
use local_quickcards\external\reset_deck;
use local_quickcards\external\submit_review;
use local_quickcards\external\update_card;
use local_quickcards\external\update_deck;
use moodle_exception;

/**
 * External web services unit tests for QuickCards.
 *
 * @package     local_quickcards
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_quickcards\external\create_deck
 * @covers      \local_quickcards\external\update_deck
 * @covers      \local_quickcards\external\delete_deck
 * @covers      \local_quickcards\external\create_card
 * @covers      \local_quickcards\external\update_card
 * @covers      \local_quickcards\external\delete_card
 * @covers      \local_quickcards\external\move_card
 * @covers      \local_quickcards\external\submit_review
 * @covers      \local_quickcards\external\reset_deck
 */
final class externallib_test extends advanced_testcase {
    /**
     * Test creating and updating a flashcard deck.
     */
    public function test_create_and_update_deck(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        // Create a new deck.
        $result = create_deck::execute('Biology', 'Cellular biology flashcards', 0);
        $result = external_api::clean_returnvalue(create_deck::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['id']);

        $deckid = $result['id'];
        $deck = $DB->get_record('local_quickcards_decks', ['id' => $deckid], '*', MUST_EXIST);
        $this->assertEquals('Biology', $deck->name);
        $this->assertEquals('Cellular biology flashcards', $deck->description);
        $this->assertEquals((int) $user->id, (int) $deck->userid);

        // Update deck.
        $updateresult = update_deck::execute($deckid, 'Advanced Biology', 'Updated description', 0);
        $updateresult = external_api::clean_returnvalue(update_deck::execute_returns(), $updateresult);

        $this->assertTrue($updateresult['success']);
        $this->assertEquals($deckid, $updateresult['id']);

        $updateddeck = $DB->get_record('local_quickcards_decks', ['id' => $deckid], '*', MUST_EXIST);
        $this->assertEquals('Advanced Biology', $updateddeck->name);
        $this->assertEquals('Updated description', $updateddeck->description);
    }

    /**
     * Test creating a deck with empty name throws exception.
     */
    public function test_create_deck_empty_name(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $this->expectException(invalid_parameter_exception::class);
        create_deck::execute('   ', 'Empty deck', 0);
    }

    /**
     * Test deleting a deck cascades to its cards and reviews.
     */
    public function test_delete_deck_cascading(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deckres = create_deck::execute('History', 'World history', 0);
        $deckid = $deckres['id'];

        $card1res = create_card::execute($deckid, 'Question 1', 'Answer 1');
        $card2res = create_card::execute($deckid, 'Question 2', 'Answer 2');

        $this->assertEquals(2, $DB->count_records('local_quickcards_cards', ['deckid' => $deckid]));
        $this->assertEquals(2, $DB->count_records('local_quickcards_reviews', ['userid' => $user->id]));

        // Delete deck.
        $delres = delete_deck::execute($deckid);
        $delres = external_api::clean_returnvalue(delete_deck::execute_returns(), $delres);

        $this->assertTrue($delres['success']);
        $this->assertEquals($deckid, $delres['id']);

        // Verify records deleted.
        $this->assertFalse($DB->record_exists('local_quickcards_decks', ['id' => $deckid]));
        $this->assertFalse($DB->record_exists('local_quickcards_cards', ['deckid' => $deckid]));
        $this->assertFalse($DB->record_exists('local_quickcards_reviews', ['cardid' => $card1res['id']]));
        $this->assertFalse($DB->record_exists('local_quickcards_reviews', ['cardid' => $card2res['id']]));
    }

    /**
     * Test user isolation on deck operations.
     */
    public function test_deck_user_isolation(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $usera = $generator->create_user();
        $userb = $generator->create_user();

        $this->setUser($usera);
        $deckres = create_deck::execute('User A Deck', '', 0);
        $deckid = $deckres['id'];

        // Switch to User B.
        $this->setUser($userb);

        // User B cannot update User A's deck.
        $this->expectException(dml_missing_record_exception::class);
        update_deck::execute($deckid, 'Hijacked', '', 0);
    }

    /**
     * Test user B cannot delete user A's deck.
     */
    public function test_delete_deck_user_isolation(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $usera = $generator->create_user();
        $userb = $generator->create_user();

        $this->setUser($usera);
        $deckres = create_deck::execute('User A Deck', '', 0);
        $deckid = $deckres['id'];

        // Switch to User B.
        $this->setUser($userb);

        $this->expectException(dml_missing_record_exception::class);
        delete_deck::execute($deckid);
    }

    /**
     * Test creating, updating and deleting a flashcard.
     */
    public function test_card_crud_operations(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deckres = create_deck::execute('Chemistry', '', 0);
        $deckid = $deckres['id'];

        // Create card.
        $cardres = create_card::execute($deckid, 'What is H2O?', 'Water');
        $cardres = external_api::clean_returnvalue(create_card::execute_returns(), $cardres);

        $this->assertTrue($cardres['success']);
        $this->assertGreaterThan(0, $cardres['id']);
        $this->assertEquals($deckid, $cardres['deckid']);

        $cardid = $cardres['id'];

        // Verify default SM-2 review schedule was created.
        $review = $DB->get_record('local_quickcards_reviews', ['cardid' => $cardid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals(sm2::DEFAULT_EASE_FACTOR, (float) $review->easefactor);
        $this->assertEquals(0, (int) $review->repetitions);
        $this->assertEquals(0, (int) $review->intervaldays);
        $this->assertNull($review->lastreview);

        // Update card.
        $updres = update_card::execute($cardid, 'What is NaCl?', 'Table salt');
        $updres = external_api::clean_returnvalue(update_card::execute_returns(), $updres);

        $this->assertTrue($updres['success']);

        $card = $DB->get_record('local_quickcards_cards', ['id' => $cardid], '*', MUST_EXIST);
        $this->assertEquals('What is NaCl?', $card->question);
        $this->assertEquals('Table salt', $card->answer);

        // Delete card.
        $delres = delete_card::execute($cardid);
        $delres = external_api::clean_returnvalue(delete_card::execute_returns(), $delres);

        $this->assertTrue($delres['success']);
        $this->assertFalse($DB->record_exists('local_quickcards_cards', ['id' => $cardid]));
        $this->assertFalse($DB->record_exists('local_quickcards_reviews', ['cardid' => $cardid]));
    }

    /**
     * Test creating card with empty question throws exception.
     */
    public function test_create_card_empty_question(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deckres = create_deck::execute('Test Deck', '', 0);
        $deckid = $deckres['id'];

        $this->expectException(invalid_parameter_exception::class);
        create_card::execute($deckid, '   ', 'Valid Answer');
    }

    /**
     * Test user isolation on card operations.
     */
    public function test_card_user_isolation(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $usera = $generator->create_user();
        $userb = $generator->create_user();

        $this->setUser($usera);
        $deckres = create_deck::execute('User A Deck', '', 0);

        $this->setUser($userb);

        // User B cannot add card to User A's deck.
        $this->expectException(dml_missing_record_exception::class);
        create_card::execute($deckres['id'], 'Hacked Question', 'Hacked Answer');
    }

    /**
     * Test user B cannot update user A's card.
     */
    public function test_update_card_user_isolation(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $usera = $generator->create_user();
        $userb = $generator->create_user();

        $this->setUser($usera);
        $deckres = create_deck::execute('User A Deck', '', 0);
        $cardres = create_card::execute($deckres['id'], 'Q1', 'A1');

        $this->setUser($userb);

        $this->expectException(dml_missing_record_exception::class);
        update_card::execute($cardres['id'], 'Modified Q', 'Modified A');
    }

    /**
     * Test moving a card between decks.
     */
    public function test_move_card(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deck1 = create_deck::execute('Deck 1', '', 0);
        $deck2 = create_deck::execute('Deck 2', '', 0);

        $card = create_card::execute($deck1['id'], 'Move me', 'Please');
        $cardid = $card['id'];

        // Move to Deck 2.
        $moveres = move_card::execute($cardid, $deck2['id']);
        $moveres = external_api::clean_returnvalue(move_card::execute_returns(), $moveres);

        $this->assertTrue($moveres['success']);
        $this->assertEquals($deck2['id'], $moveres['targetdeckid']);

        $cardrec = $DB->get_record('local_quickcards_cards', ['id' => $cardid], '*', MUST_EXIST);
        $this->assertEquals($deck2['id'], (int) $cardrec->deckid);

        // Moving to same deck throws exception.
        $this->expectException(invalid_parameter_exception::class);
        move_card::execute($cardid, $deck2['id']);
    }

    /**
     * Test moving card user isolation.
     */
    public function test_move_card_user_isolation(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $usera = $generator->create_user();
        $userb = $generator->create_user();

        $this->setUser($usera);
        $decka = create_deck::execute('User A Deck', '', 0);
        $carda = create_card::execute($decka['id'], 'Card A', 'Answer A');

        $this->setUser($userb);
        $deckb = create_deck::execute('User B Deck', '', 0);

        // User B cannot move User A's card.
        $this->expectException(dml_missing_record_exception::class);
        move_card::execute($carda['id'], $deckb['id']);
    }

    /**
     * Test submit review updates SM-2 schedule.
     */
    public function test_submit_review(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deck = create_deck::execute('Study Deck', '', 0);
        $card = create_card::execute($deck['id'], 'Prompt', 'Response');
        $cardid = $card['id'];

        // First review: Good (rating 3).
        $revres = submit_review::execute($cardid, sm2::RATING_GOOD);
        $revres = external_api::clean_returnvalue(submit_review::execute_returns(), $revres);

        $this->assertTrue($revres['success']);
        $this->assertEquals(1, $revres['repetitions']);
        $this->assertEquals(1, $revres['intervaldays']);
        $this->assertEquals(sm2::DEFAULT_EASE_FACTOR, (float) $revres['easefactor']);

        $revrec = $DB->get_record('local_quickcards_reviews', ['cardid' => $cardid], '*', MUST_EXIST);
        $this->assertEquals(1, (int) $revrec->repetitions);
        $this->assertNotNull($revrec->lastreview);
    }

    /**
     * Test submit review with invalid rating throws exception.
     */
    public function test_submit_review_invalid_rating(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deck = create_deck::execute('Study Deck', '', 0);
        $card = create_card::execute($deck['id'], 'Prompt', 'Response');

        $this->expectException(invalid_parameter_exception::class);
        submit_review::execute($card['id'], 99);
    }

    /**
     * Test resetting a deck resets all cards to 0 repetitions and default ease factor.
     */
    public function test_reset_deck(): void {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $deck = create_deck::execute('Reset Test Deck', '', 0);
        $deckid = $deck['id'];

        $card1 = create_card::execute($deckid, 'Q1', 'A1');
        $card2 = create_card::execute($deckid, 'Q2', 'A2');

        // Review both cards with rating Good.
        submit_review::execute($card1['id'], sm2::RATING_GOOD);
        submit_review::execute($card2['id'], sm2::RATING_GOOD);

        // Verify repetitions incremented.
        $rev1 = $DB->get_record('local_quickcards_reviews', ['cardid' => $card1['id']], '*', MUST_EXIST);
        $this->assertEquals(1, (int) $rev1->repetitions);

        // Reset deck.
        $resetres = reset_deck::execute($deckid);
        $resetres = external_api::clean_returnvalue(reset_deck::execute_returns(), $resetres);

        $this->assertTrue($resetres['success']);
        $this->assertEquals(2, $resetres['cardsreset']);

        // Check cards reset.
        $resrev1 = $DB->get_record('local_quickcards_reviews', ['cardid' => $card1['id']], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $resrev1->repetitions);
        $this->assertEquals(0, (int) $resrev1->intervaldays);
        $this->assertEquals(sm2::DEFAULT_EASE_FACTOR, (float) $resrev1->easefactor);
        $this->assertNull($resrev1->lastreview);

        $resrev2 = $DB->get_record('local_quickcards_reviews', ['cardid' => $card2['id']], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $resrev2->repetitions);
        $this->assertEquals(0, (int) $resrev2->intervaldays);
        $this->assertEquals(sm2::DEFAULT_EASE_FACTOR, (float) $resrev2->easefactor);
        $this->assertNull($resrev2->lastreview);
    }

    /**
     * Test guest user access is forbidden for web services.
     */
    public function test_guest_user_forbidden(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->expectException(moodle_exception::class);
        create_deck::execute('Guest Deck', '', 0);
    }
}
