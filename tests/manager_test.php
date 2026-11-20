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
use moodle_exception;

/**
 * Unit tests for QuickCards manager class.
 *
 * @package     local_quickcards
 * @category    test
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_quickcards\manager
 */
final class manager_test extends advanced_testcase {
    /**
     * Test is_enabled and require_enabled settings control.
     */
    public function test_plugin_enabled_setting(): void {
        $this->resetAfterTest();

        // Default when config is unset is true.
        unset_config('enabled', 'local_quickcards');
        $this->assertTrue(manager::is_enabled());

        // Explicitly enabled.
        set_config('enabled', 1, 'local_quickcards');
        $this->assertTrue(manager::is_enabled());

        // The require_enabled method does not throw when enabled.
        manager::require_enabled();

        // Explicitly disabled.
        set_config('enabled', 0, 'local_quickcards');
        $this->assertFalse(manager::is_enabled());

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('plugindisabled', 'local_quickcards'));
        manager::require_enabled();
    }

    /**
     * Test cohort-based access restrictions.
     */
    public function test_cohort_access_restrictions(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $cohort1 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort Alpha']);
        $cohort2 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort Beta']);

        // Add user1 to cohort1; user2 belongs to no cohort.
        cohort_add_member($cohort1->id, $user1->id);

        // No cohorts configured: everyone with capability has access.
        unset_config('allowedcohorts', 'local_quickcards');
        $this->assertEquals([], manager::get_allowed_cohort_ids());
        $this->assertTrue(manager::can_access((int) $user1->id));
        $this->assertTrue(manager::can_access((int) $user2->id));

        // Restrict to cohort1 and cohort2.
        set_config('allowedcohorts', $cohort1->id . ',' . $cohort2->id, 'local_quickcards');
        $this->assertEquals([(int) $cohort1->id, (int) $cohort2->id], manager::get_allowed_cohort_ids());

        // Member of cohort1 has access; non-member does not.
        $this->assertTrue(manager::can_access((int) $user1->id));
        $this->assertFalse(manager::can_access((int) $user2->id));

        // Site admin always bypasses cohort restrictions when plugin is enabled.
        $admin = get_admin();
        $this->assertTrue(manager::can_access((int) $admin->id));

        // When plugin is globally disabled, even cohort members and admins cannot access.
        set_config('enabled', 0, 'local_quickcards');
        $this->assertFalse(manager::can_access((int) $user1->id));
        $this->assertFalse(manager::can_access((int) $admin->id));

        // Re-enable plugin and verify require_access throws accessrestricted for non-member.
        set_config('enabled', 1, 'local_quickcards');
        manager::require_access((int) $user1->id);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('accessrestricted', 'local_quickcards'));
        manager::require_access((int) $user2->id);
    }

    /**
     * Test guest users are strictly prevented from accessing QuickCards.
     */
    public function test_guest_user_cannot_access(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        // The can_access method must return false for guest users.
        $this->assertFalse(manager::can_access());

        // The user_in_allowed_cohorts method must return false for guest users even with no cohort restriction.
        unset_config('allowedcohorts', 'local_quickcards');
        $this->assertFalse(manager::user_in_allowed_cohorts());

        // The require_access method must throw noguest exception for guest users.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('noguest', 'error'));
        manager::require_access();
    }
}
