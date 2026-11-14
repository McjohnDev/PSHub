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
 * The session lobby: where a learner waits for a class, and walks into it.
 *
 * The most important page in the product. Everything else - the catalogue, the
 * price, the review, the diary - exists so that somebody arrives here at the
 * right time and gets in.
 *
 * It answers one question and always answers it with a reason: can I go in
 * now, and if not, what should I do instead (§3.6, §15). The verdict comes
 * from session_service, which re-checks the door on every request rather than
 * trusting what the page said a minute ago (AC-10).
 *
 * THE CLOCK IS THE SERVER'S. The page prints the time the server believes it
 * is, and the countdown - when scripting is available - counts from that, not
 * from the device. A wrong device clock is ordinary on the handsets this
 * product targets, and a lobby that trusted it would open the door early for
 * exactly the learners least able to question it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\local\constants;
use local_pshub\session\join_verdict;
use local_pshub\session\session_service;

$id = required_param('id', PARAM_INT);

require_login();

$session = $DB->get_record('local_pshub_session', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $session->courseid], 'id, fullname', MUST_EXIST);

$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url(new moodle_url('/local/pshub/learn/session.php', ['id' => $id]));
$PAGE->set_pagelayout('pslearner');
$PAGE->set_title(format_string($session->title));
$PAGE->set_heading(format_string($session->title));

$service = new session_service();
$now = time();
$verdict = $service->can_join($USER->id, $id, $now);

// A verdict of "no access" is not a refusal to show the page: the learner may
// simply not have bought this course yet, and the useful thing to do is point
// them at it rather than slam a door.
$reason = $verdict->get_reason();

$opensat = $verdict->opens_at();
$courseurl = new moodle_url('/local/pshub/learn/course.php', ['id' => $course->id]);
$salesurl = new moodle_url('/local/pshub/course.php', ['id' => $course->id]);

// The room only exists once a BigBlueButton activity has been attached to the
// session. Until then the door may be open and there is still nowhere to walk
// into, and saying so is better than a button that opens nothing.
$opensistoday = $opensat && (usergetmidnight($opensat) === usergetmidnight($now));

$hasroom = !empty($session->bbb_cmid);
// Through our own door, which takes the verdict again at the instant of the
// click and records the arrival. A link straight to the activity would trust
// a page that may have been sitting open in a tab since before the class.
$joinurl = $hasroom
    ? (new moodle_url('/local/pshub/learn/join.php',
        ['id' => $session->id, 'sesskey' => sesskey()]))->out(false)
    : null;

// How long until the door opens, and until the class ends - both counted from
// the SERVER's clock and handed to the page as plain seconds. The browser then
// ticks with elapsed time since load, which is the only thing a device clock
// can be trusted for.
$opensin = $opensat ? max(0, $opensat - $now) : 0;
$endsin = ($verdict->is_granted() && $session->scheduled_end > $now)
    ? $session->scheduled_end - $now
    : 0;

// The page is complete without this: it already prints when the room opens and
// how long the class runs (AC-20). The module makes the wait legible.
$PAGE->requires->js_call_amd('local_pshub/session_clock', 'init', [$opensin, $endsin]);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_pshub/session_lobby', [
    'coursename' => format_string($course->fullname),
    'courseurl' => $courseurl->out(false),
    'title' => format_string($session->title),
    'when' => userdate($session->scheduled_start, get_string('strftimedaydatetime', 'langconfig')),
    'duration' => get_string('session_minutes', 'local_pshub', $session->duration_minutes),

    // What the server believes the time is, printed so that a learner whose
    // own clock is wrong can see the difference for themselves.
    'servertime' => userdate($now, get_string('strftimetime', 'langconfig')),

    'canjoin' => $verdict->is_granted(),
    'message' => $verdict->get_message(),
    'state' => $reason,

    'hasroom' => $hasroom,
    'joinurl' => $joinurl,
    'showroomnotice' => ($verdict->is_granted() && !$hasroom),

    'hasopensat' => (bool) $opensat,
    // The time alone when the door opens today, the day as well when it does
    // not. "The room opens at 17:45" is a lie by omission for a class that is
    // tomorrow, and it is the kind a learner only discovers by waiting.
    'opensat' => $opensat ? userdate($opensat, get_string(
        $opensistoday ? 'strftimetime' : 'strftimedaydatetime', 'langconfig')) : null,
    // "opens at 17:45" and "opens on Monday 5 October, 17:45" are two
    // sentences in French, not one with a variable in it.
    'opensistoday' => $opensistoday,
    // Seconds, for a countdown that may be added later. Computed here from the
    // server's own clock so the browser never has to guess (PSH-S05-09).
    'opensin' => $opensin,
    'endswarning' => get_string('lobby_endswarning', 'local_pshub', '{time}'),

    'needsaccess' => ($reason === join_verdict::REASON_NO_ACCESS),
    'salesurl' => $salesurl->out(false),
    'islive' => ($session->status === constants::SESSION_LIVE),
]);

echo $OUTPUT->footer();
