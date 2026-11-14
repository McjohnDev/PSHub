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
 * The learner's diary: every class they are expected at, soonest first.
 *
 * In a product where every course is taught live, this is the page a learner
 * opens most: not to read anything, but to find out when they are next due
 * somewhere and whether they can get in (§10.4).
 *
 * It is a list, not a month grid. A grid gives each day a box, and on the
 * handsets this product targets the box is too small for the only two things
 * that matter - the time, and whether the door is open.
 *
 * Every door on it comes from session_service::can_join(), through
 * agenda_service, so the agenda and the lobby can never disagree.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\calendar\agenda_service;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/pshub/learn/calendar.php'));
$PAGE->set_pagelayout('pslearner');
$PAGE->set_title(get_string('agenda_title', 'local_pshub'));
$PAGE->set_heading(get_string('agenda_title', 'local_pshub'));

$service = new agenda_service();
$now = time();

$upcoming = $service->get_upcoming_days($USER->id, $now);
$recent = $service->get_recent_days($USER->id, $now);
$exporturl = $service->get_export_url($USER->id);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/learn_agenda', [
    'upcomingdays' => $upcoming,
    'hasupcoming' => (bool) $upcoming,
    'recentdays' => $recent,
    'hasrecent' => (bool) $recent,

    // The platform's clock, printed for the same reason the lobby prints it:
    // a wrong device clock is ordinary, and this is how somebody sees it.
    'servertime' => userdate($now, get_string('strftimetime', 'langconfig')),

    'hasexport' => (bool) $exporturl,
    'exporturl' => $exporturl ? $exporturl->out(false) : null,
    'catalogueurl' => (new moodle_url('/local/pshub/catalogue.php'))->out(false),
]);

echo $OUTPUT->footer();
