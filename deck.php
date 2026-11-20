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
 * Deck management page: list, add, edit, and delete cards within a deck.
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

$deckid = required_param('id', PARAM_INT);

$context = \context_system::instance();
require_capability('local/quickcards:use', $context);

$url = new \moodle_url('/local/quickcards/deck.php', ['id' => $deckid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

$deck = \local_quickcards\manager::get_deck_with_cards($deckid, $USER->id);
if (!$deck) {
    throw new \moodle_exception('invalidrecord', 'error');
}

$PAGE->set_title($deck['name'] . ' - ' . get_string('pluginname', 'local_quickcards'));
$PAGE->set_heading($deck['name']);

// Set up navbar.
$PAGE->navbar->add(
    get_string('mycards', 'local_quickcards'),
    new \moodle_url('/local/quickcards/view.php')
);
$PAGE->navbar->add($deck['name']);

$courses = \local_quickcards\manager::get_user_courses_options($USER->id);
$otherdecks = \local_quickcards\manager::get_user_other_decks($USER->id, $deckid);

$templatecontext = [
    'deck' => $deck,
    'courses' => $courses,
    'hascourses' => !empty($courses),
    'otherdecks' => $otherdecks,
    'hasotherdecks' => !empty($otherdecks),
    'sesskey' => sesskey(),
];

// Initialize frontend AMD module.
$PAGE->requires->js_call_amd('local_quickcards/deck_manager', 'init', []);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_quickcards/deck', $templatecontext);
echo $OUTPUT->footer();
