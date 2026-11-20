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
 * Download endpoint for exporting a QuickCards deck as JSON.
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
require_sesskey();

$deckid = required_param('id', PARAM_INT);
$format = optional_param('format', 'json', PARAM_ALPHA);
$includeprogress = (bool) optional_param('includeprogress', 0, PARAM_BOOL);

if ($format === 'csv') {
    $payload = \local_quickcards\exporter::export_deck($deckid, $USER->id, false);
    $filename = \local_quickcards\exporter::generate_filename($payload['deck']['name'], false, 'csv');
    $content = \local_quickcards\exporter::export_deck_csv($deckid, $USER->id);
    $contenttype = 'text/csv; charset=utf-8';
} else {
    $payload = \local_quickcards\exporter::export_deck($deckid, $USER->id, $includeprogress);
    $filename = \local_quickcards\exporter::generate_filename($payload['deck']['name'], $includeprogress, 'json');
    $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $contenttype = 'application/json; charset=utf-8';
}

\core\session\manager::write_close();

header('Content-Type: ' . $contenttype);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo $content;
exit;
