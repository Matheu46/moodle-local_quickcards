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

use core_user\hook\extend_user_menu;
use core_user\output\user_action_menu\link;
use core\output\pix_icon;
use core\url;

/**
 * Hook callbacks handler for local_quickcards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hooks {
    /**
     * Extends the user action menu dropdown.
     *
     * @param extend_user_menu $hook The hook instance.
     */
    public static function extend_user_menu(extend_user_menu $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (!has_capability('local/quickcards:use', \context_system::instance())) {
            return;
        }

        $url = new url('/local/quickcards/view.php');
        $title = get_string('mycards', 'local_quickcards');
        $pixicon = new pix_icon('i/grades', $title);

        if (class_exists(link::class)) {
            $item = new link($url, $title, null, $pixicon);
            $hook->add_menu_item($item);
        } else if (method_exists($hook, 'add_navitem')) {
            $navitem = new \stdClass();
            $navitem->itemtype = 'link';
            $navitem->url = $url;
            $navitem->title = $title;
            $navitem->pixicon = $pixicon;
            $hook->add_navitem($navitem);
        }
    }
}
