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

/**
 * Unit tests for the SM-2 algorithm implementation.
 *
 * @package     local_quickcards
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_quickcards\sm2
 */
final class sm2_test extends advanced_testcase {
    /**
     * Test first review when user fails the card (Again).
     */
    public function test_first_review_again(): void {
        $now = 1700000000;
        $res = sm2::calculate(2.50, 0, 0, sm2::RATING_AGAIN, $now);

        $this->assertEquals(0, $res['repetitions']);
        $this->assertEquals(1, $res['intervaldays']);
        $this->assertEquals($now + DAYSECS, $res['nextreview']);
        $this->assertLessThan(2.50, $res['easefactor']);
    }

    /**
     * Test first review when user answers Good.
     */
    public function test_first_review_good(): void {
        $now = 1700000000;
        $res = sm2::calculate(2.50, 0, 0, sm2::RATING_GOOD, $now);

        $this->assertEquals(1, $res['repetitions']);
        $this->assertEquals(1, $res['intervaldays']);
        $this->assertEquals($now + DAYSECS, $res['nextreview']);
        $this->assertEquals(2.50, $res['easefactor']);
    }

    /**
     * Test second review when user answers Good.
     */
    public function test_second_review_good(): void {
        $now = 1700000000;
        $res = sm2::calculate(2.50, 1, 1, sm2::RATING_GOOD, $now);

        $this->assertEquals(2, $res['repetitions']);
        $this->assertEquals(6, $res['intervaldays']);
        $this->assertEquals($now + (6 * DAYSECS), $res['nextreview']);
    }

    /**
     * Test subsequent reviews scaling with ease factor.
     */
    public function test_subsequent_reviews(): void {
        $now = 1700000000;
        $res = sm2::calculate(2.50, 6, 2, sm2::RATING_GOOD, $now);

        $this->assertEquals(3, $res['repetitions']);
        // 6 * 2.50 = 15 days.
        $this->assertEquals(15, $res['intervaldays']);
        $this->assertEquals($now + (15 * DAYSECS), $res['nextreview']);
    }

    /**
     * Test minimum ease factor floor.
     */
    public function test_minimum_ease_factor_floor(): void {
        $res = sm2::calculate(1.35, 1, 0, sm2::RATING_AGAIN);
        $this->assertGreaterThanOrEqual(sm2::MINIMUM_EASE_FACTOR, $res['easefactor']);

        $res2 = sm2::calculate(1.30, 1, 0, sm2::RATING_AGAIN);
        $this->assertEquals(sm2::MINIMUM_EASE_FACTOR, $res2['easefactor']);
    }

    /**
     * Test Easy rating provides bonus interval.
     */
    public function test_easy_rating_bonus(): void {
        $res = sm2::calculate(2.50, 6, 2, sm2::RATING_EASY);
        $this->assertGreaterThan(2.50, $res['easefactor']);
        $this->assertGreaterThan(15, $res['intervaldays']);
    }
}
