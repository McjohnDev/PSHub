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

namespace local_pshub\calendar;

use local_pshub\local\constants;
use local_pshub\session\join_verdict;
use local_pshub\session\session_service;
use moodle_url;

/**
 * One learner's appointments, across every course they are in.
 *
 * WHY THIS EXISTS
 * In a product where every course is taught live, the thing a learner needs
 * most often is not a course page - it is the answer to "when am I next
 * expected somewhere, and can I get in". The dashboard shows the next five
 * sessions; a course page shows one course. Neither answers "what does my
 * week look like" (§10.4).
 *
 * THE AGENDA NEVER OFFERS A DOOR THE DOOR REFUSES
 * Each row's action comes from session_service::can_join(), not from comparing
 * a date. The two must agree, and the only way to guarantee that is to ask the
 * same function. A list that works out for itself that a class is "in fifteen
 * minutes, so joinable" is how a learner gets a button that lands them on a
 * refusal - and the refusal always wins, because the door re-checks (AC-10).
 *
 * THE COST, STATED
 * One verdict per row, each of which reads the session and runs the gating
 * check. That is a handful of queries per class, so the horizon is capped: an
 * agenda is a thing you look at, not a data export. The export is iCal, and
 * Moodle's own exporter serves it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agenda_service {

    /**
     * @var int How far ahead the agenda looks, in days.
     *
     * Six months, not two. A twelve-week course spans about eighty-five days,
     * and a diary that stops at sixty hides the second half of it - which is
     * the half a learner is least sure about. The cost is one verdict per row
     * and a term's worth of rows is a dozen.
     */
    public const HORIZON_DAYS = 180;

    /** @var int How far back it looks, so somebody can see what they missed. */
    public const RECENT_DAYS = 7;

    /** @var session_service The source of every verdict on this page. */
    private session_service $sessions;

    /**
     * @param session_service|null $sessions
     */
    public function __construct(?session_service $sessions = null) {
        $this->sessions = $sessions ?? new session_service();
    }

    /**
     * The learner's appointments from now to the horizon, grouped by day.
     *
     * Cancelled classes are left out: a cancelled class is not an
     * appointment, and a diary that keeps showing it is a diary somebody
     * stops trusting.
     *
     * @param int $userid
     * @param int|null $now
     * @param int $horizondays
     * @return array<int, array> One entry per day that has something in it.
     */
    public function get_upcoming_days(int $userid, ?int $now = null,
            int $horizondays = self::HORIZON_DAYS): array {

        $now = $now ?? time();
        $rows = $this->fetch($userid, $now, $now + ($horizondays * DAYSECS));

        return $this->group_by_day($rows, $userid, $now, true);
    }

    /**
     * The classes that have just been, newest first.
     *
     * Separate from the upcoming list and deliberately short. Its job is to
     * answer "did I miss something" - not to be a history, which belongs on
     * the course page beside the attendance it is evidence for.
     *
     * @param int $userid
     * @param int|null $now
     * @param int $recentdays
     * @return array<int, array>
     */
    public function get_recent_days(int $userid, ?int $now = null,
            int $recentdays = self::RECENT_DAYS): array {

        $now = $now ?? time();
        $rows = $this->fetch($userid, $now - ($recentdays * DAYSECS), $now, true);

        return array_reverse($this->group_by_day($rows, $userid, $now, false));
    }

    /**
     * How many appointments this learner has to come.
     *
     * Counted from the rows, not by building the diary: a number does not need
     * a door verdict per class. It uses the same window as the diary, so the
     * tile on the dashboard and the list on the agenda can never disagree -
     * which is the bug this replaced. The tile used to count the list it was
     * about to print, and that list is capped at five, so a learner with
     * eleven classes left was told they had five.
     *
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public function count_upcoming(int $userid, ?int $now = null): int {
        $now = $now ?? time();

        return count($this->fetch($userid, $now, $now + (self::HORIZON_DAYS * DAYSECS)));
    }

    /**
     * The learner's personal iCal subscription URL, or null.
     *
     * Moodle already exports the calendar, and the sessions are in it as
     * course events (session_service::sync_calendar_event). Re-implementing an
     * iCal writer here would mean a second thing to keep correct, and one that
     * no phone has ever tested.
     *
     * THIS URL IS A CREDENTIAL. The token is derived from the user's password
     * hash, so anyone holding the URL can read that calendar until the
     * password changes. It is never logged and never shown to anybody but its
     * owner, and the page says so in as many words.
     *
     * @param int $userid
     * @return moodle_url|null Null when the site has calendar export switched off.
     */
    public function get_export_url(int $userid): ?moodle_url {
        global $CFG, $DB;

        if (empty($CFG->enablecalendarexport)) {
            return null;
        }

        require_once($CFG->dirroot . '/calendar/lib.php');

        $user = $DB->get_record('user', ['id' => $userid], 'id, password', IGNORE_MISSING);
        if (!$user) {
            return null;
        }

        return new moodle_url('/calendar/export_execute.php', [
            'preset_what' => 'courses',
            'preset_time' => 'recentupcoming',
            'userid' => $userid,
            'authtoken' => calendar_get_export_token($user),
        ]);
    }

    /**
     * The raw session rows in a window, for courses this learner is in.
     *
     * @param int $userid
     * @param int $from
     * @param int $to
     * @param bool $includeended Past classes are 'ended'; future ones are not.
     * @return array
     */
    private function fetch(int $userid, int $from, int $to, bool $includeended = false): array {
        global $DB;

        $enrolled = enrol_get_all_users_courses($userid, true, ['id']);
        if (!$enrolled) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($enrolled), SQL_PARAMS_NAMED);

        $states = [constants::SESSION_SCHEDULED, constants::SESSION_LIVE];
        if ($includeended) {
            $states[] = constants::SESSION_ENDED;
        }
        [$statesql, $stateparams] = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 'st');

        $params += $stateparams;
        $params['from'] = $from;
        $params['to'] = $to;

        // Overlap, not containment: a class that began before the window and
        // runs into it is happening now, which is the one a learner most
        // needs to see.
        $sql = "SELECT s.id, s.courseid, s.title, s.scheduled_start, s.scheduled_end,
                       s.duration_minutes, s.status, s.bbb_cmid, c.fullname AS coursename
                  FROM {local_pshub_session} s
                  JOIN {course} c ON c.id = s.courseid
                 WHERE s.courseid {$insql}
                       AND s.status {$statesql}
                       AND s.scheduled_end >= :from
                       AND s.scheduled_start <= :to
              ORDER BY s.scheduled_start ASC";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Turn a flat list of sessions into days a template can walk.
     *
     * Days are the learner's own, not the server's: usergetmidnight() applies
     * the user's timezone, so a class at 23:30 does not land on tomorrow for
     * somebody and today for somebody else.
     *
     * @param array $rows
     * @param int $userid
     * @param int $now
     * @param bool $withverdict Past classes get no door, so they get no verdict.
     * @return array<int, array>
     */
    private function group_by_day(array $rows, int $userid, int $now, bool $withverdict): array {
        $days = [];

        foreach ($rows as $row) {
            $midnight = usergetmidnight((int) $row->scheduled_start);

            if (!isset($days[$midnight])) {
                $days[$midnight] = [
                    // A machine-readable date for <time datetime>, and NOT
                    // userdate('%Y-%m-%d'): on Windows that comes back as
                    // "2026-11-7", because Moodle strips the day's leading
                    // zero to work around a platform difference. Valid
                    // ISO-8601 needs the zero, so the date is formatted
                    // directly in the user's own timezone.
                    'dayiso' => $this->iso_date($midnight),
                    'daylabel' => $this->day_label($midnight, $now),
                    'istoday' => ($midnight === usergetmidnight($now)),
                    'sessions' => [],
                ];
            }

            $days[$midnight]['sessions'][] = $this->present($row, $userid, $now, $withverdict);
        }

        ksort($days);

        return array_values($days);
    }

    /**
     * A day as YYYY-MM-DD in the learner's own timezone.
     *
     * @param int $timestamp
     * @return string
     */
    private function iso_date(int $timestamp): string {
        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone(\core_date::get_user_timezone_object());

        return $date->format('Y-m-d');
    }

    /**
     * "Today", "Tomorrow", or the day written out.
     *
     * A date alone makes a learner count; "today" and "tomorrow" are what
     * they are actually asking.
     *
     * @param int $midnight
     * @param int $now
     * @return string
     */
    private function day_label(int $midnight, int $now): string {
        $today = usergetmidnight($now);

        if ($midnight === $today) {
            return get_string('agenda_today', 'local_pshub');
        }
        if ($midnight === usergetmidnight($now + DAYSECS)) {
            return get_string('agenda_tomorrow', 'local_pshub');
        }
        if ($midnight === usergetmidnight($now - DAYSECS)) {
            return get_string('agenda_yesterday', 'local_pshub');
        }

        return userdate($midnight, get_string('strftimedaydate', 'langconfig'));
    }

    /**
     * One row of the agenda, with its action decided by the door.
     *
     * @param \stdClass $row
     * @param int $userid
     * @param int $now
     * @param bool $withverdict
     * @return array
     */
    private function present(\stdClass $row, int $userid, int $now, bool $withverdict): array {
        $out = [
            'sessionid' => (int) $row->id,
            'courseid' => (int) $row->courseid,
            'coursename' => format_string($row->coursename),
            'title' => format_string($row->title),
            'starttime' => userdate((int) $row->scheduled_start,
                get_string('strftimetime', 'langconfig')),
            'duration' => get_string('session_minutes', 'local_pshub',
                (int) $row->duration_minutes),
            'url' => (new moodle_url('/local/pshub/learn/session.php',
                ['id' => $row->id]))->out(false),
            'courseurl' => (new moodle_url('/local/pshub/learn/course.php',
                ['id' => $row->courseid]))->out(false),
            'islive' => ($row->status === constants::SESSION_LIVE),
            'isended' => ($row->status === constants::SESSION_ENDED),
            'canjoin' => false,
            'note' => null,
            'state' => $row->status,
        ];

        if (!$withverdict) {
            return $out;
        }

        // The same question the lobby asks, answered by the same function.
        $verdict = $this->sessions->can_join($userid, (int) $row->id, $now);

        // A room that does not exist yet is not a door to offer, however open
        // the verdict is - the lobby makes the same distinction.
        $out['canjoin'] = $verdict->is_granted() && !empty($row->bbb_cmid);
        $out['state'] = $verdict->get_reason();

        if ($verdict->is_granted() && empty($row->bbb_cmid)) {
            $out['note'] = get_string('agenda_noroom', 'local_pshub');
        } else if (!$verdict->is_granted() && $verdict->get_reason() !== join_verdict::REASON_TOO_EARLY) {
            // "Too early" needs no note here: the time is in the row already,
            // and repeating it as a sentence on every future class turns the
            // agenda into a wall of apology.
            $out['note'] = $verdict->get_message();
        }

        return $out;
    }
}
