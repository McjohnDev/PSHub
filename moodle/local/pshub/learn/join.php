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
 * The door itself: the one place a learner passes from our pages into a class.
 *
 * WHY THIS IS NOT A LINK STRAIGHT TO THE ACTIVITY
 * The lobby that offered the button may be minutes old - left open in a tab,
 * reloaded from the back button, bookmarked. The verdict is therefore taken
 * again HERE, at the instant of the click, which is what AC-10 means by
 * "gated on every attempt, never on a cached client-side flag". A page that
 * decided once and trusted itself afterwards is a door that stays open after
 * the class has ended.
 *
 * WHAT THIS RECORDS
 * An arrival. `local_pshub_attendance` gets a row with `joined_at` and
 * `counted_present = 0` - present is not the same as arrived, and the ninety
 * seconds rule that decides it (AC-14) is reconciled after the session, not
 * guessed at the door.
 *
 * WHAT STILL NEEDS A SERVER
 * Minting the signed BigBlueButton URL, and with it the moderator/viewer
 * split, belongs to mod_bigbluebuttonbn: it holds the meeting passwords and
 * talks to the server. This file hands over to it once the decision is made.
 * The role itself is decided here and recorded, so the two cannot disagree
 * later (§8.1).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_pshub\session\session_service;

$id = required_param('id', PARAM_INT);

require_login();
require_sesskey();

$session = $DB->get_record('local_pshub_session', ['id' => $id], '*', MUST_EXIST);
$lobbyurl = new moodle_url('/local/pshub/learn/session.php', ['id' => $id]);

$PAGE->set_context(context_course::instance($session->courseid));
$PAGE->set_url(new moodle_url('/local/pshub/learn/join.php', ['id' => $id]));

// Taken again, now. Anything but a grant goes back to the lobby, which knows
// how to explain each refusal and what to do about it.
$verdict = (new session_service())->can_join($USER->id, $id);
if (!$verdict->is_granted()) {
    redirect($lobbyurl, $verdict->get_message(), null,
        \core\output\notification::NOTIFY_WARNING);
}

if (empty($session->bbb_cmid)) {
    redirect($lobbyurl, get_string('lobby_noroom', 'local_pshub'), null,
        \core\output\notification::NOTIFY_WARNING);
}

// Moderator or viewer, decided from the capability rather than from the
// session's teacherid alone: a course can have more than one teacher, and a
// stand-in covering a class must not arrive as a viewer unable to share a
// screen (§8.2).
$coursecontext = context_course::instance($session->courseid);
$ismoderator = has_capability('moodle/course:manageactivities', $coursecontext);

// The arrival, recorded before the hand-over, because the hand-over leaves
// this site. Unique on (sessionid, userid), so a learner whose connection
// drops and who comes back updates their row instead of adding a second.
$existing = $DB->get_record('local_pshub_attendance', ['sessionid' => $id, 'userid' => $USER->id]);

if ($existing) {
    $existing->joined_at = $existing->joined_at ?: time();
    $DB->update_record('local_pshub_attendance', $existing);
} else {
    $DB->insert_record('local_pshub_attendance', (object) [
        'sessionid' => $id,
        'userid' => $USER->id,
        'participant_role' => $ismoderator ? 'moderator' : 'viewer',
        'joined_at' => time(),
        'left_at' => 0,
        'duration_seconds' => 0,
        // Arrived is not present. The rule that decides presence (AC-14) runs
        // after the session, against the time actually spent in the room.
        'counted_present' => 0,
        'xp_awarded' => 0,
    ]);
}

// Over to the module, which holds the passwords and speaks to the server.
redirect(new moodle_url('/mod/bigbluebuttonbn/view.php', ['id' => $session->bbb_cmid]));
