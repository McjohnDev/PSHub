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
 * The learner's own space: what they are taking, and what is next.
 *
 * Where a learner lands after signing in, in place of Moodle's dashboard
 * (AC-17). Everything on it is read from data that exists - enrolments,
 * scheduled sessions, the points ledger - and anything the platform does not
 * record yet simply is not here. A dashboard that invents a figure is worse
 * than one that admits it has none.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\learner\learner_service;

// This IS the page behind the login wall, so the redirect is the point: a
// visitor who arrives here is sent to sign in and comes back afterwards.
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/learn/index.php'));
$PAGE->set_pagelayout('pslearner');
$PAGE->set_title(get_string('learn_title', 'local_pshub'));
$PAGE->set_heading(get_string('learn_title', 'local_pshub'));

$service = new learner_service();
$courses = $service->get_courses($USER->id);
$sessions = $service->get_upcoming_sessions($USER->id);

// Session times are formatted here, not in the template: a template that
// formats a date has to know the reader's timezone and language, and this one
// already knows both.
$sessionrows = [];
foreach ($sessions as $session) {
    $sessionrows[] = [
        'title' => format_string($session->title),
        'coursename' => format_string($session->coursename),
        'when' => userdate($session->scheduled_start, get_string('strftimedaydatetime', 'langconfig')),
        'duration' => get_string('card_duration', 'local_pshub', $session->duration_minutes),
        'islive' => ($session->status === \local_pshub\local\constants::SESSION_LIVE),
        // The session, not the course: somebody tapping a class at ten to six
        // is going to the class.
        'url' => (new moodle_url('/local/pshub/learn/session.php',
            ['id' => $session->id]))->out(false),
    ];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/learn_dashboard', [
    'greeting' => get_string('learn_greeting', 'local_pshub', $USER->firstname),
    'catalogueurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),

    'coursecount' => count($courses),
    // NOT count($sessions): that list is the next five, and a tile that
    // counts the list it is about to print tells a learner with eleven
    // classes left that they have five. The agenda owns the real number, and
    // owning it in one place is what keeps the tile and the diary agreeing.
    'sessioncount' => (new \local_pshub\calendar\agenda_service())->count_upcoming($USER->id),
    'xp' => $service->get_xp($USER->id),

    'courses' => $courses,
    'hascourses' => (bool) $courses,

    'sessions' => $sessionrows,
    'hassessions' => (bool) $sessionrows,
]);

echo $OUTPUT->footer();
