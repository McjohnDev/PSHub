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
 * The teacher's console: their courses, where each one stands, what is next.
 *
 * A teacher is not kept out of Moodle the way a learner is - a course is
 * authored there, and this page says so. What it adds is the half Moodle
 * cannot show: the review status of each course, the brief it has to satisfy,
 * and the live sessions they are down to run (§6.8).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\teacher\teacher_service;

require_login();

$context = context_system::instance();
require_capability('local/pshub:viewteacherportal', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/teacher/index.php'));
$PAGE->set_pagelayout('psteacher');
$PAGE->set_title(get_string('teacher_title', 'local_pshub'));
$PAGE->set_heading(get_string('teacher_title', 'local_pshub'));

$service = new teacher_service();
$courses = $service->get_courses($USER->id);
$totals = $service->get_totals($courses);
$sessions = $service->get_upcoming_sessions($USER->id);
$briefs = $service->get_briefs($USER->id);

// Dates are formatted here, not in the template: formatting one means knowing
// the reader's timezone and language, and this page already knows both.
$sessionrows = [];
foreach ($sessions as $session) {
    $sessionrows[] = [
        'title' => format_string($session->title),
        'coursename' => format_string($session->coursename),
        'when' => userdate($session->scheduled_start, get_string('strftimedaydatetime', 'langconfig')),
        'duration' => get_string('card_duration', 'local_pshub', $session->duration_minutes),
        'islive' => ($session->status === \local_pshub\local\constants::SESSION_LIVE),
    ];
}

$briefrows = [];
foreach ($briefs as $brief) {
    $briefrows[] = [
        'coursename' => $brief->coursename ? format_string($brief->coursename) : null,
        'hascourse' => !empty($brief->coursename),
        'brief' => format_text($brief->brief, FORMAT_PLAIN),
        'when' => $brief->assigned_at
            ? userdate($brief->assigned_at, get_string('strftimedate', 'langconfig'))
            : null,
    ];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/teacher_overview', [
    'greeting' => get_string('teacher_greeting', 'local_pshub', $USER->firstname),

    'published' => $totals->published,
    'pending' => $totals->pending,
    'learners' => $totals->learners,

    'courses' => $courses,
    'hascourses' => (bool) $courses,
    // The hub's own form, not Moodle's: a course created in Moodle has no
    // language, level or price, so it could never reach the catalogue.
    'createurl' => (new moodle_url('/local/pshub/teacher/course.php'))->out(false),
    'cancreate' => has_capability('local/pshub:submitcourse', $context),

    'sessions' => $sessionrows,
    'hassessions' => (bool) $sessionrows,

    'briefs' => $briefrows,
    'hasbriefs' => (bool) $briefrows,
]);

echo $OUTPUT->footer();
