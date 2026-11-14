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
 * The administration space.
 *
 * The product's own console: what is waiting to be validated, what is selling,
 * what exists. It never replaces Moodle's site administration - roles, cohorts
 * and competency frameworks stay there, and the note at the foot of the
 * sidebar says so (§6.9, §12.1).
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */

$currenturl = $PAGE->url->out_omit_querystring();
$home = (new moodle_url('/local/pshub/admin/index.php'))->out(false);

$templatecontext = theme_pslanguagehub_shell_context($home) + [
    'navlabel' => get_string('nav_section_admin', 'theme_pslanguagehub'),
    'nav' => [
        theme_pslanguagehub_nav_item('/local/pshub/admin/index.php',
            get_string('nav_overview', 'theme_pslanguagehub'), 'progress', $currenturl),
        theme_pslanguagehub_nav_item('/local/pshub/admin/featured.php',
            get_string('nav_featured', 'local_pshub'), 'check', $currenturl),
        // Course types are configuration, and configuration lives in Moodle's
        // admin pages where its access control already is. The link is honest
        // about leaving the console: the sidebar note says where it goes.
        theme_pslanguagehub_nav_item('/local/pshub/admin/coursetypes.php',
            get_string('nav_types', 'theme_pslanguagehub'), 'tag', $currenturl),
    ],
    'rolelabel' => get_string('role_admin', 'theme_pslanguagehub'),

    'hassearch' => false,
    'topactions' => [[
        'url' => (new moodle_url('/admin/search.php'))->out(false),
        'label' => get_string('action_open_moodle', 'theme_pslanguagehub'),
        'primary' => true,
    ]],
    'sidenote' => [
        'title' => get_string('moodlenote_title', 'theme_pslanguagehub'),
        'text' => get_string('moodlenote_admin', 'theme_pslanguagehub'),
    ],
];

echo $OUTPUT->render_from_template('theme_pslanguagehub/psapp', $templatecontext);
