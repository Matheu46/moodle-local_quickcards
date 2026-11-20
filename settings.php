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

/**
 * Settings configuration page for QuickCards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_quickcards', get_string('pluginname', 'local_quickcards'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        require_once($CFG->dirroot . '/cohort/lib.php');

        // Enable/disable QuickCards globally.
        $settings->add(new admin_setting_configcheckbox(
            'local_quickcards/enabled',
            get_string('setting_enabled', 'local_quickcards'),
            get_string('setting_enabled_desc', 'local_quickcards'),
            1
        ));

        // Restrict access to specific cohorts (empty = all users with capability).
        $allcohorts = cohort_get_all_cohorts(0, 0);
        $cohortchoices = [];
        foreach ($allcohorts['cohorts'] as $cohort) {
            $cohortchoices[$cohort->id] = format_string($cohort->name);
        }

        if (!empty($cohortchoices)) {
            $settings->add(new admin_setting_configmultiselect(
                'local_quickcards/allowedcohorts',
                get_string('setting_allowedcohorts', 'local_quickcards'),
                get_string('setting_allowedcohorts_desc', 'local_quickcards'),
                [],
                $cohortchoices
            ));
        } else {
            $settings->add(new admin_setting_description(
                'local_quickcards/allowedcohorts_empty',
                get_string('setting_allowedcohorts', 'local_quickcards'),
                get_string('setting_nocohorts', 'local_quickcards')
            ));
        }
    }
}
