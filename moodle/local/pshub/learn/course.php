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
 * A course, as the learner who takes it sees it.
 *
 * Moodle's own course page is built for a teacher assembling material: edit
 * mode, drawers, a section index. A learner on this platform comes to a course
 * for one reason - the live classes - so this page leads with the sessions,
 * the progress through them, and whatever material the course actually holds
 * (AC-17).
 *
 * Everything on it is read from data that exists. No teacher is named until
 * one is assigned, no progress bar is drawn until sessions are scheduled, and
 * no tab is offered onto an empty section.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\learner\learner_service;

$id = required_param('id', PARAM_INT);

$backtocourses = new moodle_url('/local/pshub/learn/index.php');
$course = $DB->get_record('course', ['id' => $id]);
if (!$course) {
    throw new moodle_exception('learn_course_unavailable', 'local_pshub', $backtocourses);
}

// Moodle's enrolment check is the gate, because enrolment is what actually
// opens a course. Someone who is not enrolled is sent to /enrol/index.php,
// which the hub redirects to the course's own sales page - so arriving here
// without access ends on "here is what this course costs", not on a stack
// trace.
require_login($course);

$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url(new moodle_url('/local/pshub/learn/course.php', ['id' => $course->id]));
$PAGE->set_pagelayout('pslearner');
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

$service = new learner_service();
$detail = $service->get_course_detail($USER->id, $course->id);

// A Moodle course that the hub does not publish is not part of this product,
// and this page cannot describe it: no price, no level, no sessions.
if (!$detail) {
    throw new moodle_exception('learn_course_unavailable', 'local_pshub', $backtocourses);
}

$presented = $detail->course;

// The line under the title, assembled from what this course actually records.
// Each part is a fact in the database; nothing is here to fill the line out.
$meta = [];

if ($detail->teachers) {
    $names = array_map(static fn($teacher) => $teacher->fullname, $detail->teachers);
    $meta[] = get_string('learn_course_teacher', 'local_pshub', implode(', ', $names));
}

if ($presented->cefr_level) {
    $meta[] = get_string('learn_course_level', 'local_pshub', $presented->cefr_level);
}

if ($presented->is_one_to_one) {
    $meta[] = get_string('learn_course_onetoone', 'local_pshub');
} else if ($presented->max_participants) {
    $meta[] = get_string('learn_course_group', 'local_pshub', $presented->max_participants);
}

// The tabs of the approved screen, as anchors to the sections that exist. A
// link to a heading needs no scripting, survives the back button and can be
// shared - and a section that is not in this course has no tab (AC-20).
$tabs = [['label' => get_string('course_about', 'local_pshub'), 'anchor' => 'ps-about']];
foreach ($detail->groups as $group) {
    $tabs[] = ['label' => $group->label, 'anchor' => 'ps-group-' . $group->key];
}

// Dates are formatted here rather than in the template: formatting one means
// knowing the reader's timezone and language, and this page already knows
// both.
$sessionrows = [];
foreach ($detail->sessions as $session) {
    if ($session->attended) {
        $state = 'done';
        $statelabel = get_string('learn_session_attended', 'local_pshub');
    } else if ($session->islive) {
        $state = 'live';
        $statelabel = get_string('learn_live_now', 'local_pshub');
    } else if ($session->ispast) {
        $state = 'past';
        $statelabel = get_string('learn_session_past', 'local_pshub');
    } else {
        $state = 'next';
        $statelabel = get_string('learn_session_upcoming', 'local_pshub');
    }

    $sessionrows[] = [
        'url' => (new moodle_url('/local/pshub/learn/session.php',
            ['id' => $session->id]))->out(false),
        'title' => format_string($session->title),
        'when' => userdate($session->scheduled_start, get_string('strftimedaydatetime', 'langconfig')),
        'duration' => get_string('session_minutes', 'local_pshub', $session->duration_minutes),
        'state' => $state,
        'statelabel' => $statelabel,
        'hasjoin' => (bool) $session->joinurl,
        'joinurl' => $session->joinurl,
    ];
}

$progress = false;
if ($detail->progress) {
    $progress = [
        'percent' => $detail->progress->percent,
        'percentlabel' => get_string('learn_progress_percent', 'local_pshub', $detail->progress->percent),
        'sessionlabel' => get_string('learn_progress_sessions', 'local_pshub', $detail->progress),
    ];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/learn_course', [
    'course' => $presented,
    'crumburl' => $backtocourses->out(false),
    'crumblabel' => get_string('learn_courses_title', 'local_pshub'),
    'crumbhere' => get_string('learn_crumb_detail', 'local_pshub'),

    'durationlabel' => get_string('learn_course_duration', 'local_pshub',
        $presented->session_duration_minutes),
    'meta' => array_map(static fn($part) => ['text' => $part], $meta),
    'hasmeta' => (bool) $meta,
    'teacherpending' => !$detail->teachers,

    'tabs' => $tabs,
    'groups' => $detail->groups,
    'hasgroups' => (bool) $detail->groups,

    'sessions' => $sessionrows,
    'hassessions' => (bool) $sessionrows,
    'livesession' => $detail->livesession ? [
        'joinurl' => $detail->livesession->joinurl,
    ] : null,
    'islive' => (bool) $detail->livesession,

    'progress' => $progress,
]);

echo $OUTPUT->footer();
