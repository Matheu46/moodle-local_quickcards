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
 * Library functions and hooks for local_quickcards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the user navigation menu with a link to the QuickCards center.
 *
 * @param navigation_node $parentnode The parent navigation node.
 * @param stdClass $user The user object.
 * @param context_user $usercontext The user context.
 * @param stdClass $course The course object.
 * @param context_course $coursecontext The course context.
 */
function local_quickcards_extend_navigation_user(
    navigation_node $parentnode,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
) {
    global $USER;

    if (!isloggedin() || isguestuser() || (int) $user->id !== (int) $USER->id) {
        return;
    }

    if (!\local_quickcards\manager::is_enabled()) {
        return;
    }

    if (!has_capability('local/quickcards:use', \context_system::instance())) {
        return;
    }

    $url = new \moodle_url('/local/quickcards/view.php');
    $node = navigation_node::create(
        get_string('mycards', 'local_quickcards'),
        $url,
        navigation_node::NODETYPE_LEAF,
        'local_quickcards',
        'local_quickcards',
        new \pix_icon('i/grades', '')
    );
    $parentnode->add_node($node);
}
