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
 * The login-flow layout: sign in, sign up, forgotten password.
 *
 * Boost's own version of this file renders theme_boost/login, which pulls in
 * core/login_layout - and that template is already ours. So this file exists
 * for exactly two pieces of context Boost does not pass:
 *
 *  - `issignup`, so the brand panel can say "create your account" on the page
 *    where someone is creating one, rather than welcoming them back;
 *  - `year`, for the copyright line, which otherwise had to be left off
 *    because Boost's context carries no date.
 *
 * It renders theme_boost/login rather than a template of our own, so the page
 * head, body attributes and end-of-body scripts stay Boost's problem.
 *
 * @package    theme_pslanguagehub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $SITE */

// Only set when an administrator has written custom instructions; the template
// falls back to its own copy otherwise. Reproduced from Boost because the
// template it renders still expects it.
$leftinstructions = !empty($CFG->auth_instructions)
    ? format_text($CFG->auth_instructions, FORMAT_MOODLE, ['context' => context_system::instance()])
    : null;

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true,
        ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(),
    'leftinstructions' => $leftinstructions,

    // login-signup is Moodle's pagetype for /login/signup.php. Matching on the
    // pagetype rather than the URL keeps this working if the route ever moves.
    'issignup' => ($PAGE->pagetype === 'login-signup'),

    'year' => userdate(time(), '%Y'),
];

echo $OUTPUT->render_from_template('theme_boost/login', $templatecontext);
