<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The learner's own space: a sidebar, a search bar, and the page.
 *
 * WHY THIS LAYOUT EXISTS
 * Until it did, a learner who signed in landed on Moodle's dashboard - course
 * overview blocks, an edit-mode toggle, a timeline - which is precisely what
 * AC-17 says a learner must never meet. The public pages had their own shell
 * already; this is the same idea behind the login wall.
 *
 * The markup is shared with the teacher and administration spaces: this file
 * only chooses the doors. Like the public one it declares no block regions and
 * no drawers, which is what keeps it cheap to re-reconcile at the next Moodle
 * major (spec §12.1).
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

$currenturl = $PAGE->url->out_omit_querystring();
$home = (new moodle_url('/local/pshub/learn/index.php'))->out(false);

$templatecontext = theme_pslanguagehub_shell_context($home) + [
    'navlabel' => get_string('nav_section_main', 'theme_pslanguagehub'),
    'nav' => [
        theme_pslanguagehub_nav_item('/local/pshub/learn/index.php',
            get_string('nav_progress', 'theme_pslanguagehub'), 'progress', $currenturl),
        // Second, not last: where every course is taught live, "when am I next
        // due somewhere" gets asked more often than "what else is there".
        theme_pslanguagehub_nav_item('/local/pshub/learn/calendar.php',
            get_string('nav_agenda', 'theme_pslanguagehub'), 'calendar', $currenturl),
        theme_pslanguagehub_nav_item('/local/pshub/catalogue.php',
            get_string('nav_explore', 'theme_pslanguagehub'), 'book', $currenturl),
    ],
    'rolelabel' => get_string('role_learner', 'theme_pslanguagehub'),

    'hassearch' => true,
    'searchaction' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
    'searchplaceholder' => get_string('search_placeholder_app', 'theme_pslanguagehub'),
];

echo $OUTPUT->render_from_template('theme_pslanguagehub/psapp', $templatecontext);
