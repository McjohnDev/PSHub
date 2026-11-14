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
 * A course's live sessions: the diary, and how to add to it.
 *
 * This is where the product's one promise becomes a date. Everything else -
 * the catalogue, the price, the review - exists so that this list is not
 * empty (§3.5).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\form\session_form;
use local_pshub\local\constants;
use local_pshub\session\session_service;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
$coursecontext = context_course::instance($courseid);

// Scheduling a class is teaching it: the capability is checked in the course's
// own context, so nobody puts a session in somebody else's diary.
require_capability('moodle/course:manageactivities', $coursecontext);

$context = context_system::instance();
$pageurl = new moodle_url('/local/pshub/teacher/sessions.php', ['courseid' => $courseid]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('psteacher');
$PAGE->set_title(get_string('sessions_title', 'local_pshub'));
$PAGE->set_heading(get_string('sessions_title', 'local_pshub'));

$service = new session_service();

// Cancelling is a POST with a sesskey: a class learners were told about is not
// called off by following a link.
if ($action === 'cancel' && $id && confirm_sesskey()) {
    $service->cancel($id);
    redirect($pageurl, get_string('session_cancelled', 'local_pshub'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$editing = null;
if ($id && $action === 'edit') {
    $editing = $DB->get_record('local_pshub_session',
        ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
}

$form = new session_form($pageurl->out(false));

if ($form->is_cancelled()) {
    redirect($pageurl);
}

if ($editing) {
    $form->set_data([
        'id' => $editing->id,
        'courseid' => $courseid,
        'title' => $editing->title,
        'start' => $editing->scheduled_start,
        'minutes' => $editing->duration_minutes,
    ]);
} else {
    $form->set_data(['courseid' => $courseid]);
}

if ($data = $form->get_data()) {
    if (!empty($data->id)) {
        $service->reschedule((int) $data->id, (int) $data->start, (int) $data->minutes,
            $data->title);
        $message = get_string('session_rescheduled', 'local_pshub');
    } else {
        $service->schedule($courseid, (int) $USER->id, $data->title,
            (int) $data->start, (int) $data->minutes);
        $message = get_string('session_scheduled', 'local_pshub');
    }

    redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

// The diary: everything still to come first, then what has happened.
$sessions = $DB->get_records('local_pshub_session', ['courseid' => $courseid],
    'scheduled_start ASC');

$upcoming = [];
$past = [];
$now = time();

foreach ($sessions as $session) {
    $row = [
        'id' => $session->id,
        'title' => format_string($session->title),
        'when' => userdate($session->scheduled_start, get_string('strftimedaydatetime', 'langconfig')),
        'duration' => get_string('session_minutes', 'local_pshub', $session->duration_minutes),
        'iscancelled' => ($session->status === constants::SESSION_CANCELLED),
        'editurl' => (new moodle_url($pageurl, ['action' => 'edit', 'id' => $session->id]))->out(false),
        'cancelurl' => (new moodle_url($pageurl,
            ['action' => 'cancel', 'id' => $session->id, 'sesskey' => sesskey()]))->out(false),
        'sesskey' => sesskey(),
        'actionurl' => $pageurl->out(false),
        // A class that has not happened can still be moved or called off.
        'ismovable' => ($session->status === constants::SESSION_SCHEDULED
            && $session->scheduled_end >= $now),
    ];

    if ($session->scheduled_end >= $now && $session->status !== constants::SESSION_CANCELLED) {
        $upcoming[] = $row;
    } else {
        $past[] = $row;
    }
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/teacher_sessions', [
    'coursename' => format_string($course->fullname),
    'backurl' => (new moodle_url('/local/pshub/teacher/index.php'))->out(false),
    'isediting' => (bool) $editing,
    'formhtml' => $form->render(),
    'upcoming' => $upcoming,
    'hasupcoming' => (bool) $upcoming,
    'past' => array_reverse($past),
    'haspast' => (bool) $past,
]);

echo $OUTPUT->footer();
