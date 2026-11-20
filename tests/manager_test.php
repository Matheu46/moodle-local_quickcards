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
}
