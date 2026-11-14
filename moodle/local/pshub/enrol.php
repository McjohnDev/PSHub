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
 * Enrol in a free course.
 *
 * AC-9: the access right and the Moodle enrolment are created together or not
 * at all. enrolment_service owns that atomicity; this page only carries the
 * request to it and decides where the learner lands afterwards.
 *
 * Paid courses are NOT handled here. They go through checkout in S7, and until
 * that exists a paid course simply reports that payment is required rather
 * than pretending to enrol.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_pshub\enrolment\enrolment_service;

$id = required_param('id', PARAM_INT);

$coursedetail = new moodle_url('/local/pshub/course.php', ['id' => $id]);

// Acting requires an account, even though browsing did not (AC-2). Plain
// require_login() REDIRECTS to the login page and returns the visitor here
// afterwards, which is the behaviour we want.
//
// Do not pass $preventredirect = true: that makes this throw instead, so an
// anonymous visitor who clicks "join" gets a 500 rather than a login form.
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/pshub/enrol.php', ['id' => $id]));

// A state-changing request, so it must not be reachable by following a link
// from elsewhere or by a prefetching browser.
require_sesskey();

$verdict = (new enrolment_service())->enrol_free($USER->id, $id);

if ($verdict->is_granted()) {
    redirect(new moodle_url('/course/view.php', ['id' => $id]));
}

// Nothing was written. Say why, in the learner's words.
redirect(
    $coursedetail,
    $verdict->get_message(),
    null,
    \core\output\notification::NOTIFY_WARNING
);
