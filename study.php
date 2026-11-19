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
 * Interactive active recall study session page for QuickCards.
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

$deckid = optional_param('deckid', 0, PARAM_INT);

$context = \context_system::instance();
require_capability('local/quickcards:use', $context);

$url = new \moodle_url('/local/quickcards/study.php');
if ($deckid > 0) {
    $url->param('deckid', $deckid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

$deckname = '';
$backurl = new \moodle_url('/local/quickcards/view.php');
if ($deckid > 0) {
    $deck = $DB->get_record('local_quickcards_decks', ['id' => $deckid, 'userid' => $USER->id], '*', MUST_EXIST);
    $deckname = format_string($deck->name, true, ['context' => $context]);
    $backurl = new \moodle_url('/local/quickcards/deck.php', ['id' => $deckid]);

    $PAGE->set_title(get_string('study', 'local_quickcards') . ': ' . $deckname);
    $PAGE->set_heading($deckname);

    $PAGE->navbar->add(
        get_string('mycards', 'local_quickcards'),
        new \moodle_url('/local/quickcards/view.php')
    );
    $PAGE->navbar->add($deckname, $backurl);
    $PAGE->navbar->add(get_string('study', 'local_quickcards'));
} else {
    $PAGE->set_title(get_string('studyall', 'local_quickcards'));
    $PAGE->set_heading(get_string('studyall', 'local_quickcards'));

    $PAGE->navbar->add(
        get_string('mycards', 'local_quickcards'),
        $backurl
    );
    $PAGE->navbar->add(get_string('study', 'local_quickcards'));
}

$duecards = \local_quickcards\manager::get_due_cards_for_study($USER->id, $deckid);
$totaldue = count($duecards);
$cardsjson = json_encode($duecards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$templatecontext = [
    'hasdue' => $totaldue > 0,
    'totaldue' => $totaldue,
    'deckid' => $deckid,
    'deckname' => $deckname,
    'hasdeck' => !empty($deckname),
    'backurl' => $backurl->out(false),
    'cardsjson' => $cardsjson,
    'keyboard_shortcuts_html' => get_string('keyboard_shortcuts', 'local_quickcards'),
];

// Initialize frontend study session AMD module.
$PAGE->requires->js_call_amd('local_quickcards/study_session', 'init', [
    (int) $deckid,
    $backurl->out(false),
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_quickcards/study', $templatecontext);
echo $OUTPUT->footer();
