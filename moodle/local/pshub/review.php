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
 * Submit, approve or reject a course.
 *
 * Every state change arrives here as a POST carrying a sesskey, and the page
 * that precedes it is a confirmation - so nothing changes state because
 * somebody followed a link, and nothing needs JavaScript to be confirmed
 * (AC-20).
 *
 * The decision itself belongs to review_service: this file collects the
 * intention, the service enforces the rules and writes the audit row.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_pshub\review\review_service;

$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$confirmed = optional_param('confirm', 0, PARAM_BOOL);
$comment = optional_param('comment', '', PARAM_TEXT);

require_login();

$course = $DB->get_record('course', ['id' => $id], 'id, fullname', MUST_EXIST);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pshub/review.php', ['id' => $id, 'action' => $action]));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

$service = new review_service();

// Where the person came from, and where they go back to. A teacher submits
// from their own console; an administrator decides from theirs.
$isteacheraction = ($action === 'submit');
$returnurl = new moodle_url($isteacheraction
    ? '/local/pshub/teacher/index.php'
    : '/local/pshub/admin/index.php');
$PAGE->set_pagelayout($isteacheraction ? 'psteacher' : 'psadmin');

if (!in_array($action, ['submit', 'approve', 'reject'], true)) {
    throw new moodle_exception('invalidaction');
}

// Do it. The sesskey check is what makes this a deliberate act by this person,
// and the capability check inside the service is what makes it a permitted one.
if ($confirmed && confirm_sesskey()) {
    switch ($action) {
        case 'submit':
            $service->submit($id);
            $done = get_string('review_submitted', 'local_pshub');
            break;
        case 'approve':
            $service->approve($id);
            $done = get_string('review_approved', 'local_pshub');
            break;
        default:
            // An empty comment comes back to the form rather than throwing:
            // the person is three words away from finishing, not in error.
            if (trim($comment) === '') {
                $error = get_string('review_comment_required', 'local_pshub');
                break;
            }
            $service->reject($id, $comment);
            $done = get_string('review_rejected', 'local_pshub');
    }

    if (!empty($done)) {
        redirect($returnurl, $done, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Not confirmed yet: ask.
$problems = ($action === 'submit') ? $service->submission_problems($id) : [];

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/review_action', [
    'coursename' => format_string($course->fullname),
    'actionurl' => (new moodle_url('/local/pshub/review.php'))->out(false),
    'id' => $id,
    'action' => $action,
    'sesskey' => sesskey(),
    'cancelurl' => $returnurl->out(false),

    'isreject' => ($action === 'reject'),
    'comment' => $comment,
    'haserror' => !empty($error),
    'error' => $error ?? null,

    'question' => ($action === 'approve')
        ? get_string('review_confirm_approve', 'local_pshub', format_string($course->fullname))
        : null,
    'submitlabel' => get_string('review_' . $action, 'local_pshub'),

    'hasproblems' => (bool) $problems,
    'problems' => $problems ? get_string('review_blocked', 'local_pshub', implode(' ', $problems)) : null,
]);

echo $OUTPUT->footer();
