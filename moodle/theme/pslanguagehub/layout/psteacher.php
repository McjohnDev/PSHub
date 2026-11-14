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
 * The teacher's space.
 *
 * The same shell as the learner's, with the teacher's doors on it. A teacher
 * is not kept out of Moodle the way a learner is - authoring a course happens
 * there, and the note at the foot of the sidebar says so plainly rather than
 * leaving them to hunt for an editor this portal does not carry (§6.8).
 *
 * There is no search box: nothing here searches anything yet, and a box that
 * does nothing is worse than no box.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

$currenturl = $PAGE->url->out_omit_querystring();
$home = (new moodle_url('/local/pshub/teacher/index.php'))->out(false);

$templatecontext = theme_pslanguagehub_shell_context($home) + [
    'navlabel' => get_string('nav_section_teacher', 'theme_pslanguagehub'),
    'nav' => [
        theme_pslanguagehub_nav_item('/local/pshub/teacher/index.php',
            get_string('nav_overview', 'theme_pslanguagehub'), 'progress', $currenturl),
    ],
    'rolelabel' => get_string('role_teacher', 'theme_pslanguagehub'),

    'hassearch' => false,
    'topactions' => [[
        'url' => (new moodle_url('/my/courses.php'))->out(false),
        'label' => get_string('action_open_moodle', 'theme_pslanguagehub'),
        'primary' => true,
    ]],
    'sidenote' => [
        'title' => get_string('moodlenote_title', 'theme_pslanguagehub'),
        'text' => get_string('moodlenote_teacher', 'theme_pslanguagehub'),
    ],
];

echo $OUTPUT->render_from_template('theme_pslanguagehub/psapp', $templatecontext);
