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
 * Main student dashboard for QuickCards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$context = \context_system::instance();
require_capability('local/quickcards:use', $context);

$url = new \moodle_url('/local/quickcards/view.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_quickcards'));
$PAGE->set_heading(get_string('mycards', 'local_quickcards'));

$decks = \local_quickcards\manager::get_user_decks($USER->id);
$stats = \local_quickcards\manager::get_user_stats($USER->id);
$courses = \local_quickcards\manager::get_user_courses_options($USER->id);

$studyallurl = new \moodle_url('/local/quickcards/study.php');

$templatecontext = [
    'hasdecks' => !empty($decks),
    'decks' => $decks,
    'totaldecks' => $stats['totaldecks'],
    'totalcards' => $stats['totalcards'],
    'totaldue' => $stats['totaldue'],
    'hasdue' => $stats['hasdue'],
    'studyallurl' => $studyallurl->out(false),
    'courses' => $courses,
    'hascourses' => !empty($courses),
    'sesskey' => sesskey(),
];

// Initialize frontend AMD module.
$PAGE->requires->js_call_amd('local_quickcards/deck_manager', 'init', []);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_quickcards/view', $templatecontext);
echo $OUTPUT->footer();
