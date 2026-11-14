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

namespace local_pshub\session;

use local_pshub\audit\audit_service;
use local_pshub\local\constants;
use stdClass;

/**
 * Scheduling live sessions, and deciding who may walk into one.
 *
 * THE CAP IS NOT A SUGGESTION (§8.3, AC-4). A session's length is bounded here
 * at write time, so no row can exist claiming ninety minutes. That is only the
 * first of three layers: the second is BigBlueButton's own `duration`, which
 * `bbbext_pshub` injects at meeting creation, and the third is the sweeper
 * task. See docs/SPIKE-50-MIN-CAP.md - this layer alone would be a promise
 * kept only by well-behaved software.
 *
 * TIME IS THE SERVER'S. Every comparison here uses the server clock and the
 * caller may pass one explicitly for tests. Nothing reads a device clock:
 * on the handsets this product targets a wrong clock is ordinary, and a join
 * window that trusted it would open the wrong door for exactly the learners
 * least able to question it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_service {

    /** @var int How long before the start a learner may walk in (§3.6). */
    public const JOIN_WINDOW_MINUTES = 15;

    /** @var audit_service */
    private audit_service $audit;

    /**
     * @param audit_service|null $audit
     */
    public function __construct(?audit_service $audit = null) {
        $this->audit = $audit ?? new audit_service();
    }

    /**
     * The platform's maximum session length, in minutes.
     *
     * The setting is an admin_setting_configduration and therefore stores
     * SECONDS; reading it as minutes gives a cap of 3000 and a rule that can
     * never bite.
     *
     * @return int
     */
    public function cap_minutes(): int {
        $seconds = (int) get_config('local_pshub', 'sessioncap');

        return $seconds > 0 ? (int) round($seconds / MINSECS) : constants::DEFAULT_SESSION_MINUTES;
    }

    /**
     * Schedule a session on a course.
     *
     * @param int $courseid
     * @param int $teacherid
     * @param string $title
     * @param int $start Unix timestamp.
     * @param int $minutes Requested length; bounded by the cap.
     * @return int The new session id.
     * @throws \moodle_exception
     */
    public function schedule(int $courseid, int $teacherid, string $title, int $start,
            int $minutes): int {
        global $DB;

        $title = trim($title);
        if ($title === '') {
            throw new \moodle_exception('session_title_required', 'local_pshub');
        }

        $minutes = $this->bounded_minutes($minutes);

        $session = new stdClass();
        $session->courseid = $courseid;
        $session->teacherid = $teacherid;
        $session->bbb_cmid = null;
        $session->title = $title;
        $session->scheduled_start = $start;
        $session->scheduled_end = $start + ($minutes * MINSECS);
        $session->duration_minutes = $minutes;
        $session->status = constants::SESSION_SCHEDULED;
        $session->actual_start = 0;
        $session->actual_end = 0;
        $session->recording_enabled = 0;
        $session->timecreated = time();
        $session->timemodified = time();

        $session->id = $DB->insert_record('local_pshub_session', $session);

        $this->sync_calendar_event($session);

        $this->audit->record(audit_service::ACTION_SESSION_SCHEDULED, audit_service::TARGET_SESSION,
            (int) $session->id, null, $this->state($session), $teacherid);

        return (int) $session->id;
    }

    /**
     * Move a session, or change its length.
     *
     * @param int $sessionid
     * @param int $start
     * @param int $minutes
     * @param string|null $title Null leaves it as it was.
     * @return void
     * @throws \moodle_exception
     */
    public function reschedule(int $sessionid, int $start, int $minutes,
            ?string $title = null): void {
        global $DB;

        $session = $DB->get_record('local_pshub_session', ['id' => $sessionid], '*', MUST_EXIST);

        if ($session->status !== constants::SESSION_SCHEDULED) {
            // A class that has started or finished is history, not a plan.
            throw new \moodle_exception('session_not_movable', 'local_pshub');
        }

        $before = $this->state($session);

        // The title too: the form offers it when editing, and a change the
        // form accepted and the service dropped is the worst kind of bug -
        // nothing fails, and the teacher believes the new name is saved.
        if ($title !== null) {
            $title = trim($title);
            if ($title === '') {
                throw new \moodle_exception('session_title_required', 'local_pshub');
            }
            $session->title = $title;
        }

        $minutes = $this->bounded_minutes($minutes);
        $session->scheduled_start = $start;
        $session->scheduled_end = $start + ($minutes * MINSECS);
        $session->duration_minutes = $minutes;
        $session->timemodified = time();

        $DB->update_record('local_pshub_session', $session);
        $this->sync_calendar_event($session);

        // A moved class is a new appointment, so its reminders are owed again
        // (PSH-S08-04). Clearing the rows hands the decision back to the task,
        // which will not send a reminder whose moment passed before the
        // session existed - so moving a class to this evening does not fire
        // yesterday's "in 24 hours".
        $DB->delete_records('local_pshub_reminder', ['sessionid' => $sessionid]);

        $this->audit->record(audit_service::ACTION_SESSION_RESCHEDULED, audit_service::TARGET_SESSION,
            $sessionid, $before, $this->state($session));
    }

    /**
     * Call a session off.
     *
     * Cancelled rather than deleted: learners were told it would happen, and
     * the record of that is worth keeping.
     *
     * @param int $sessionid
     * @return void
     */
    public function cancel(int $sessionid): void {
        global $DB;

        $session = $DB->get_record('local_pshub_session', ['id' => $sessionid], '*', MUST_EXIST);

        if ($session->status === constants::SESSION_CANCELLED) {
            return;
        }

        $before = $this->state($session);

        $session->status = constants::SESSION_CANCELLED;
        $session->timemodified = time();
        $DB->update_record('local_pshub_session', $session);
        $this->remove_calendar_event($sessionid);

        $this->audit->record(audit_service::ACTION_SESSION_CANCELLED, audit_service::TARGET_SESSION,
            $sessionid, $before, $this->state($session));
    }

    /**
     * Put the session in the course calendar, or move the entry that is there.
     *
     * ONE EVENT, NOT TWO. A `course` event is visible to everybody enrolled in
     * the course, which is the teacher and the learners at once - writing a
     * user event each would mean n rows to keep in step and a learner enrolled
     * tomorrow seeing nothing.
     *
     * Moodle's calendar is the right home for this rather than a list of our
     * own: it is what feeds the learner's dashboard, the month view, the
     * upcoming-events block and the iCal export, and a date a learner can put
     * in their phone is worth more than one they can only read on our page
     * (§10.4).
     *
     * @param stdClass $session
     * @return void
     */
    private function sync_calendar_event(stdClass $session): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $existing = $DB->get_record('event', [
            'component' => 'local_pshub',
            'instance' => $session->id,
            'eventtype' => 'course',
        ]);

        $data = new stdClass();
        $data->name = format_string($session->title);
        $data->description = get_string('calendar_description', 'local_pshub');
        $data->format = FORMAT_PLAIN;
        $data->courseid = $session->courseid;
        $data->groupid = 0;
        $data->userid = 0;
        $data->modulename = '';
        $data->instance = $session->id;
        $data->eventtype = 'course';
        $data->type = CALENDAR_EVENT_TYPE_STANDARD;
        $data->timestart = $session->scheduled_start;
        $data->timeduration = $session->duration_minutes * MINSECS;
        $data->timesort = $session->scheduled_start;
        $data->visible = 1;
        $data->component = 'local_pshub';

        if ($existing) {
            $event = \calendar_event::load($existing->id);
            $event->update($data, false);
            return;
        }

        \calendar_event::create($data, false);
    }

    /**
     * Rewrite a session's calendar entry from the session row.
     *
     * WHY THIS IS PUBLIC AND THE SYNC IS NOT
     * Scheduling, moving and cancelling a class all keep the calendar in step
     * on their own, and nothing else should be touching it. But the calendar
     * entry is a SECOND copy of the appointment, and a second copy drifts: a
     * restore, an import, a direct correction in the database, a script. When
     * it has drifted there was no way to put it right short of moving the
     * class and moving it back, which writes two audit rows for a change
     * nobody made.
     *
     * This is a repair, so it records nothing: the session row is the truth
     * and the entry is being made to match it. A cancelled class loses its
     * entry, which is the same rule cancel() applies.
     *
     * @param int $sessionid
     * @return void
     */
    public function resync_calendar(int $sessionid): void {
        global $DB;

        $session = $DB->get_record('local_pshub_session', ['id' => $sessionid], '*', MUST_EXIST);

        if ($session->status === constants::SESSION_CANCELLED) {
            $this->remove_calendar_event($sessionid);
            return;
        }

        $this->sync_calendar_event($session);
    }

    /**
     * Take a session out of the calendar.
     *
     * Deleted rather than left behind greyed out: an appointment that is not
     * happening should stop appearing in the month view, and the cancellation
     * itself is recorded on the session row and in the audit trail.
     *
     * @param int $sessionid
     * @return void
     */
    private function remove_calendar_event(int $sessionid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $existing = $DB->get_record('event', [
            'component' => 'local_pshub',
            'instance' => $sessionid,
            'eventtype' => 'course',
        ]);

        if ($existing) {
            \calendar_event::load($existing->id)->delete();
        }
    }

    /**
     * Whether this person may walk into this session right now, and why not.
     *
     * Four questions, in the order that gives the most useful answer first
     * (§3.6, AC-10):
     *
     *  1. Does the session still exist as an appointment - not cancelled?
     *  2. Is it the right time? The door opens fifteen minutes early and
     *     closes when the session ends.
     *  3. Is there room? Capacity is the course's own group size.
     *  4. Does this person still have access? Re-checked at the door, never
     *     trusted from when they enrolled - an access right can have expired
     *     between the two.
     *
     * @param int $userid
     * @param int $sessionid
     * @param int|null $now Server time; injectable for tests.
     * @return join_verdict
     */
    public function can_join(int $userid, int $sessionid, ?int $now = null): join_verdict {
        global $DB;

        $now = $now ?? time();
        $session = $DB->get_record('local_pshub_session', ['id' => $sessionid]);

        if (!$session) {
            return new join_verdict(false, join_verdict::REASON_UNKNOWN);
        }

        if ($session->status === constants::SESSION_CANCELLED) {
            return new join_verdict(false, join_verdict::REASON_CANCELLED, $session);
        }

        if ($session->status === constants::SESSION_ENDED || $now > $session->scheduled_end) {
            return new join_verdict(false, join_verdict::REASON_ENDED, $session);
        }

        $opens = $session->scheduled_start - (self::JOIN_WINDOW_MINUTES * MINSECS);
        if ($now < $opens) {
            return new join_verdict(false, join_verdict::REASON_TOO_EARLY, $session);
        }

        if ($this->is_full($session, $userid)) {
            return new join_verdict(false, join_verdict::REASON_FULL, $session);
        }

        // Two questions, not one, and a test is what caught the difference.
        //
        // gating_service answers "MAY this person obtain access", which on a
        // free course is yes to anybody who asks. That is the right answer for
        // a paywall and the wrong one for a door: being in the room also
        // requires an ACTIVE ENROLMENT. It is the same check that keeps out a
        // learner whose enrolment was suspended on a course that happens to be
        // free.
        $context = \context_course::instance((int) $session->courseid, IGNORE_MISSING);
        if (!$context || !is_enrolled($context, $userid, '', true)) {
            return new join_verdict(false, join_verdict::REASON_NO_ACCESS, $session);
        }

        // And the right itself still has to hold: an enrolment can outlive the
        // payment that justified it.
        $gating = new \local_pshub\access\gating_service();
        if (!$gating->check($userid, (int) $session->courseid, $now)->is_granted()) {
            return new join_verdict(false, join_verdict::REASON_NO_ACCESS, $session);
        }

        return new join_verdict(true, join_verdict::REASON_OK, $session);
    }

    /**
     * Whether the room is full for somebody who is not already in it.
     *
     * Capacity is the course's group size. A one-to-one course holds one
     * learner; a course with no stated size is not capped, because inventing
     * a number would turn people away for a rule nobody set.
     *
     * @param stdClass $session
     * @param int $userid
     * @return bool
     */
    private function is_full(stdClass $session, int $userid): bool {
        global $DB;

        $hub = $DB->get_record('local_pshub_course', ['courseid' => $session->courseid]);
        if (!$hub) {
            return false;
        }

        $capacity = $hub->is_one_to_one ? 1 : (int) ($hub->max_participants ?? 0);
        if ($capacity <= 0) {
            return false;
        }

        // Somebody already counted present is not taking a new place: a
        // learner whose connection dropped must be able to come back in.
        if ($DB->record_exists('local_pshub_attendance',
                ['sessionid' => $session->id, 'userid' => $userid])) {
            return false;
        }

        return $DB->count_records('local_pshub_attendance', ['sessionid' => $session->id]) >= $capacity;
    }

    /**
     * A length the platform will honour.
     *
     * @param int $minutes
     * @return int
     * @throws \moodle_exception
     */
    private function bounded_minutes(int $minutes): int {
        if ($minutes < 10) {
            throw new \moodle_exception('session_too_short', 'local_pshub');
        }

        // Silently bounded rather than refused: the cap is the platform's, and
        // a teacher who asks for sixty gets fifty rather than an error about a
        // rule they did not set. The form tells them before they submit.
        return min($minutes, $this->cap_minutes());
    }

    /**
     * What the audit trail records about a session.
     *
     * @param stdClass $session
     * @return array
     */
    private function state(stdClass $session): array {
        return [
            'courseid' => (int) $session->courseid,
            'teacherid' => (int) $session->teacherid,
            'scheduled_start' => (int) $session->scheduled_start,
            'duration_minutes' => (int) $session->duration_minutes,
            'status' => $session->status,
            'title' => $session->title,
        ];
    }
}
