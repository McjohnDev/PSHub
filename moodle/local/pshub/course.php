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
 * Public course detail.
 *
 * Visible to anyone. The decision this page makes is not "may you see this"
 * but "what should the button say", and that comes from the gating service so
 * the page can never disagree with what happens on the next click.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_pshub\access\gating_service;
use local_pshub\catalogue\catalogue_service;
use local_pshub\local\constants;

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/course.php', ['id' => $id]));
$PAGE->set_pagelayout('pspublic');

$course = (new catalogue_service())->get_course($id);

// An unpublished or unknown course does not exist as far as the public is
// concerned, however the visitor arrived at the URL (AC-1).
//
// Sent back to the catalogue with a plain sentence rather than thrown as an
// exception. A learner following a stale link, or a course withdrawn for
// review since they bookmarked it, has done nothing wrong and should not meet
// an error page (AC-17). Throwing here also returned HTTP 500, which is the
// wrong answer to "this is not available".
if (!$course) {
    redirect(
        new moodle_url('/local/pshub/catalogue.php'),
        get_string('denied_not_available', 'local_pshub'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

// -- What the button should say -----------------------------------------------

$cta = null;
$ctanote = null;
$ctanotice = null;

if (!isloggedin() || isguestuser()) {
    // AC-2: browsing is open, acting is not. The visitor is invited to sign in
    // at the moment it actually matters, and comes back here afterwards.
    $cta = [
        'url' => (new moodle_url('/login/index.php'))->out(false),
        'label' => get_string('cta_signin_to_enrol', 'local_pshub'),
    ];
    $ctanote = get_string('cta_browse_freely', 'local_pshub');
} else {
    // The button reflects the gating verdict rather than guessing from the
    // price, so it always agrees with what the next click will do.
    $verdict = (new gating_service())->check($USER->id, $course->courseid);

    // Granted is not the same as enrolled, and on a FREE course the two come
    // apart: gating answers "may this person obtain access", which for a free
    // course is yes to anybody. Offering "open the course" to somebody who is
    // not enrolled sent them to /course/view.php, which bounced them to the
    // enrolment page, which bounced them back here - a loop that never
    // enrolled anyone. The same distinction the session door needed (§3.6).
    $enrolled = is_enrolled(context_course::instance($course->courseid), $USER->id, '', true);

    if ($verdict->is_granted() && $enrolled) {
        $cta = [
            'url' => (new moodle_url('/local/pshub/learn/course.php',
                ['id' => $course->courseid]))->out(false),
            'label' => get_string('cta_open_course', 'local_pshub'),
        ];
    } else if ($verdict->is_granted() && $course->isfree) {
        // Free, and not joined yet: one click does it.
        $cta = [
            'url' => (new moodle_url('/local/pshub/enrol.php',
                ['id' => $course->courseid, 'sesskey' => sesskey()]))->out(false),
            'label' => get_string('cta_enrol', 'local_pshub'),
        ];
    } else if ($verdict->is_granted()) {
        // Paid, paid for, and not yet enrolled - which the validation path
        // normally fixes the moment a payment is validated. Saying so beats a
        // button that loops.
        $ctanote = get_string('cta_access_pending_enrolment', 'local_pshub');
    } else if ($verdict->get_reason() === constants::DENY_PAYMENT_PENDING) {
        // Informational, not an error. Money has arrived; a person is checking.
        $ctanotice = $OUTPUT->notification($verdict->get_message(),
            \core\output\notification::NOTIFY_INFO);
    } else if ($course->isfree) {
        // A free course can be joined here and now. A paid one has nowhere to
        // go until checkout lands in S7, so it says why rather than offering a
        // button that cannot work.
        $cta = [
            'url' => (new moodle_url('/local/pshub/enrol.php',
                ['id' => $course->courseid, 'sesskey' => sesskey()]))->out(false),
            'label' => get_string('cta_enrol', 'local_pshub'),
        ];
    } else {
        $ctanote = $verdict->get_message();
    }
}

// -- What a place on this course carries --------------------------------------
// Only what the platform provides today, and only what this course's own record
// says. Nothing in this list is a promise the product cannot keep.

$included = [
    ['text' => get_string('course_included_live', 'local_pshub', $course->session_duration_minutes)],
    ['text' => get_string('course_included_board', 'local_pshub')],
];

$included[] = ['text' => $course->is_one_to_one
    ? get_string('course_included_onetoone', 'local_pshub')
    : get_string('course_included_group', 'local_pshub')];

// The dates this course actually runs. A visitor deciding whether to pay is
// deciding whether they can be there on those evenings, and until now the page
// could not tell them (PSH-S03-04). Only sessions still to come, and never a
// cancelled one: that is not an appointment.
$sessions = $DB->get_records_select('local_pshub_session',
    'courseid = :courseid AND scheduled_end >= :now AND status <> :cancelled',
    [
        'courseid' => $course->courseid,
        'now' => time(),
        'cancelled' => \local_pshub\local\constants::SESSION_CANCELLED,
    ],
    'scheduled_start ASC', '*', 0, 4);

$sessionrows = [];
foreach ($sessions as $session) {
    $sessionrows[] = [
        'title' => format_string($session->title),
        'when' => userdate($session->scheduled_start, get_string('strftimedaydatetime', 'langconfig')),
        'duration' => get_string('session_minutes', 'local_pshub', $session->duration_minutes),
    ];
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/course', [
    'sessions' => $sessionrows,
    'hassessions' => (bool) $sessionrows,
    'course' => $course,
    'catalogueurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
    'cta' => $cta,
    'ctanote' => $ctanote,
    'ctanotice' => $ctanotice,
    'included' => $included,
]);

echo $OUTPUT->footer();
