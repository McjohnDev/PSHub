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

namespace local_pshub\task;

use local_pshub\local\constants;
use stdClass;

/**
 * Reminders before a live class: 24 hours, 1 hour, 10 minutes (AC-6).
 *
 * THE UNIQUE INDEX IS THE GUARANTEE, NOT A CHECK.
 * `local_pshub_reminder` is unique on (sessionid, offset_minutes, audience).
 * This task writes that row BEFORE sending, and a duplicate key is how it
 * learns the reminder has already gone. A `record_exists()` first would be a
 * check with a gap in it: two cron runs overlapping, or two web servers, and
 * both would read "not sent" before either wrote (§10.2).
 *
 * THE MESSAGE NEVER SAYS "IN ONE HOUR".
 * It says when the class starts. Cron can be late - a backed-up queue, a
 * server that was down, a machine that slept - and a reminder that says "in
 * one hour" ninety minutes late is worse than no reminder. The offset decides
 * only WHEN to send.
 *
 * A REMINDER WHOSE MOMENT PASSED BEFORE THE SESSION EXISTED IS NOT SENT.
 * Scheduling a class for this evening should not fire yesterday's reminder the
 * instant it is saved.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_session_reminders extends \core\task\scheduled_task {

    /** @var int[] How long before the start each reminder goes out, in minutes. */
    public const OFFSETS = [1440, 60, 10];

    /** @var string The person running the class. */
    private const AUDIENCE_TEACHER = 'teacher';

    /** @var string Everyone enrolled who is not staff. */
    private const AUDIENCE_LEARNER = 'learner';

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_reminders', 'local_pshub');
    }

    /**
     * Send whatever is due.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();

        foreach (self::OFFSETS as $index => $offset) {
            $seconds = $offset * MINSECS;

            // EACH REMINDER HAS ITS OWN WINDOW, and it closes when the next
            // one opens. A test caught why this matters: without it, a class
            // fifty minutes away had BOTH the day-before and the hour-before
            // reminder fall due at once - the first of them twenty-three hours
            // late - and the learner got two messages in the same second
            // saying the same thing.
            //
            // So the day-before reminder runs from T-24h to T-1h, the
            // hour-before from T-1h to T-10min, the last from T-10min to the
            // start. If cron is down for a whole window that reminder is
            // missed, which is right: by then the next one is the one worth
            // sending.
            $closes = self::OFFSETS[$index + 1] ?? 0;
            $closesseconds = $closes * MINSECS;

            $sql = "SELECT s.*, c.fullname AS coursename
                      FROM {local_pshub_session} s
                      JOIN {course} c ON c.id = s.courseid
                     WHERE s.status IN (:scheduled, :live)
                           AND s.scheduled_start > :now
                           AND s.scheduled_start - :seconds <= :now2
                           AND s.scheduled_start - :closes > :now3
                           AND s.timecreated <= s.scheduled_start - :seconds2";

            $due = $DB->get_records_sql($sql, [
                'scheduled' => constants::SESSION_SCHEDULED,
                'live' => constants::SESSION_LIVE,
                'now' => $now,
                'now2' => $now,
                'now3' => $now,
                'seconds' => $seconds,
                'seconds2' => $seconds,
                'closes' => $closesseconds,
            ]);

            foreach ($due as $session) {
                $this->remind($session, $offset);
            }
        }
    }

    /**
     * Send one session's reminder at one offset, to both audiences.
     *
     * @param stdClass $session
     * @param int $offset
     * @return void
     */
    private function remind(stdClass $session, int $offset): void {
        $context = \context_course::instance((int) $session->courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        if ($this->claim($session->id, $offset, self::AUDIENCE_TEACHER)) {
            $teacher = \core_user::get_user((int) $session->teacherid);
            if ($teacher && !$teacher->deleted) {
                $this->send($teacher, $session);
            }
        }

        if (!$this->claim($session->id, $offset, self::AUDIENCE_LEARNER)) {
            return;
        }

        // Everyone enrolled who is not staff there. The same definition the
        // consoles use, so the counts and the recipients cannot disagree.
        foreach (get_enrolled_users($context, '', 0, 'u.*') as $user) {
            if (has_capability('moodle/course:manageactivities', $context, $user->id)) {
                continue;
            }
            $this->send($user, $session);
        }
    }

    /**
     * Take ownership of one reminder, or discover somebody already has.
     *
     * The insert IS the claim. A duplicate key means it has gone already,
     * which is success for this task and the whole of AC-6's idempotency.
     *
     * @param int $sessionid
     * @param int $offset
     * @param string $audience
     * @return bool True when this run is the one that should send.
     */
    private function claim(int $sessionid, int $offset, string $audience): bool {
        global $DB;

        try {
            $DB->insert_record('local_pshub_reminder', (object) [
                'sessionid' => $sessionid,
                'offset_minutes' => $offset,
                'audience' => $audience,
                'sent_at' => time(),
            ]);

            return true;
        } catch (\dml_exception $e) {
            // The unique index refused it: already sent. Not an error.
            return false;
        }
    }

    /**
     * One reminder, in the recipient's own language.
     *
     * @param stdClass $user
     * @param stdClass $session
     * @return void
     */
    private function send(stdClass $user, stdClass $session): void {
        $when = userdate($session->scheduled_start,
            get_string_manager()->get_string('strftimedaydatetime', 'langconfig', null, $user->lang ?? null),
            99, false);

        $a = (object) [
            'session' => format_string($session->title),
            'course' => format_string($session->coursename),
            'when' => $when,
        ];

        $subject = get_string_manager()->get_string(
            'notify_reminder_subject', 'local_pshub', $a->session, $user->lang ?? null);
        $body = get_string_manager()->get_string(
            'notify_reminder_body', 'local_pshub', $a, $user->lang ?? null);

        $message = new \core\message\message();
        $message->component = 'local_pshub';
        $message->name = 'sessionreminder';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/local/pshub/learn/session.php',
            ['id' => $session->id]))->out(false);
        $message->contexturlname = $subject;

        message_send($message);
    }
}
