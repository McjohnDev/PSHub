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
 * Create a hub course, or correct one.
 *
 * Both halves at once: the Moodle course that holds the teaching, and the row
 * that puts it in the catalogue. Creating through Moodle's own form gives a
 * course with no language, no level and no price - so it would never reach the
 * catalogue, and the teacher would not be told why (§3.4).
 *
 * This page is also how a rejected course gets fixed. Without it the loop the
 * review workflow describes is a dead end: told the level is missing, the
 * teacher would have nowhere to set it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\course\course_service;
use local_pshub\form\course_form;

$id = optional_param('id', 0, PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/pshub:submitcourse', $context);

$returnurl = new moodle_url('/local/pshub/teacher/index.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/teacher/course.php', $id ? ['id' => $id] : []));
$PAGE->set_pagelayout('psteacher');

$service = new course_service();
$hub = null;
$current = null;

if ($id) {
    $hub = $DB->get_record('local_pshub_course', ['courseid' => $id], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

    // Editing a course means teaching it. The capability is checked in the
    // course's own context, so a teacher cannot edit somebody else's.
    require_capability('moodle/course:manageactivities', context_course::instance($id));

    if (!$service->can_edit($hub)) {
        throw new moodle_exception('course_locked', 'local_pshub', $returnurl->out(false));
    }

    $current = (object) [
        'id' => $id,
        'fullname' => $course->fullname,
        'summary' => $course->summary,
        'lang_code' => $hub->lang_code,
        'cefr_level' => $hub->cefr_level,
        'audience' => $hub->audience,
        'session_duration_minutes' => $hub->session_duration_minutes,
        'is_one_to_one' => $hub->is_one_to_one,
        'max_participants' => $hub->max_participants,
        'access_type' => $hub->access_type,
        'price_amount' => $hub->price_amount,
    ];
}

$title = $id
    ? get_string('course_form_edit', 'local_pshub')
    : get_string('course_form_create', 'local_pshub');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$form = new course_form($PAGE->url->out(false));

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($current) {
    $form->set_data($current);
}

if ($data = $form->get_data()) {
    if ($id) {
        $service->update($id, $data);
        $message = get_string('course_form_updated', 'local_pshub');
    } else {
        $id = $service->create($data);
        $message = get_string('course_form_created', 'local_pshub');
    }

    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

echo html_writer::start_div('ps-page ps-page--narrow');
echo html_writer::start_div('ps-page__head');
echo html_writer::tag('h1', $title, ['class' => 'ps-page__title']);
echo html_writer::tag('p', get_string('course_form_lede', 'local_pshub'),
    ['class' => 'ps-page__lede']);
echo html_writer::end_div();

echo html_writer::start_div('ps-side-card');
$form->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
