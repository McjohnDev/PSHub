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

namespace local_pshub\learner;

use local_pshub\catalogue\catalogue_service;
use local_pshub\local\constants;
use stdClass;

/**
 * What a learner's own space knows about them.
 *
 * Everything here is read from data that exists: the courses they are enrolled
 * in, the sessions scheduled on those courses, the points in their ledger.
 * Where the approved design shows something the platform does not yet record -
 * a quiz average, a certificate count - this service returns nothing rather
 * than a number, and the page leaves the space out. A dashboard that invents a
 * figure is worse than one that admits it has none.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_service {

    /** @var catalogue_service Presents a course the same way the catalogue does. */
    private catalogue_service $catalogue;

    /**
     * @param catalogue_service|null $catalogue
     */
    public function __construct(?catalogue_service $catalogue = null) {
        $this->catalogue = $catalogue ?? new catalogue_service();
    }

    /**
     * The hub courses this learner is enrolled in.
     *
     * Enrolment is Moodle's, because that is what actually opens a course; the
     * platform's access rights decide whether an enrolment may be created
     * (§3.7), not whether it exists. Reading enrolments here means the
     * dashboard cannot disagree with what the learner can really open.
     *
     * Courses that are not part of the hub - anything an administrator set up
     * directly in Moodle - are left out: this is the product's space, not a
     * mirror of the LMS.
     *
     * @param int $userid
     * @return stdClass[] Presented courses, see catalogue_service::present().
     */
    public function get_courses(int $userid): array {
        global $DB;

        $enrolled = enrol_get_all_users_courses($userid, true, ['id']);
        if (!$enrolled) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($enrolled), SQL_PARAMS_NAMED);
        $params['published'] = constants::REVIEW_PUBLISHED;

        $sql = "SELECT p.*, c.fullname, c.shortname, c.summary, c.summaryformat
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
                 WHERE p.courseid {$insql} AND p.review_status = :published
              ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql, $params);

        $courses = [];
        foreach ($records as $record) {
            $course = $this->catalogue->present($record);
            // Inside the learner's own space a course opens; it is not sold
            // again. The link goes to the learner's course page, not to the
            // sales page - and not through /course/view.php, which would only
            // bounce them here anyway (one redirect less on a 2G connection).
            $course->url = (new \moodle_url('/local/pshub/learn/course.php',
                ['id' => $course->courseid]))->out(false);
            $course->ctalabel = get_string('learn_open_course', 'local_pshub');
            // No price on a course they already have. In the catalogue the
            // figure is the whole point; here it is the cost of something
            // already paid for, which answers a question nobody is asking.
            $course->hideprice = true;
            $courses[] = $course;
        }

        return $courses;
    }

    /**
     * The learner's next live sessions, soonest first.
     *
     * Only sessions on courses they are enrolled in, only ones still to come,
     * and only those still scheduled - a cancelled class is not an
     * appointment.
     *
     * @param int $userid
     * @param int $limit
     * @return stdClass[] With courseid, coursename, title, start (timestamp), duration.
     */
    public function get_upcoming_sessions(int $userid, int $limit = 5): array {
        global $DB;

        $enrolled = enrol_get_all_users_courses($userid, true, ['id']);
        if (!$enrolled) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($enrolled), SQL_PARAMS_NAMED);
        $params['now'] = time();
        $params['scheduled'] = constants::SESSION_SCHEDULED;
        $params['live'] = constants::SESSION_LIVE;

        $sql = "SELECT s.id, s.courseid, s.title, s.scheduled_start, s.duration_minutes,
                       s.status, c.fullname AS coursename
                  FROM {local_pshub_session} s
                  JOIN {course} c ON c.id = s.courseid
                 WHERE s.courseid {$insql}
                       AND s.scheduled_end >= :now
                       AND s.status IN (:scheduled, :live)
              ORDER BY s.scheduled_start ASC";

        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Everything the learner's own course page can say truthfully.
     *
     * The page shows what EXISTS: the course as the catalogue presents it, the
     * people Moodle records as teaching it, the activities actually in the
     * course, the sessions actually scheduled, and the ones this learner was
     * actually counted present at. Where there is nothing - no teacher
     * assigned yet, no session booked - the page says so rather than drawing a
     * shape with nothing in it.
     *
     * @param int $userid
     * @param int $courseid
     * @return stdClass|false course, teachers, groups, sessions, progress,
     *         livesession - or false when this is not a published hub course.
     */
    public function get_course_detail(int $userid, int $courseid) {
        $course = $this->catalogue->get_course($courseid);
        if (!$course) {
            return false;
        }

        $sessions = $this->get_course_sessions($userid, $courseid);

        $detail = new stdClass();
        $detail->course = $course;
        $detail->teachers = $this->get_teachers($courseid);
        $detail->groups = $this->get_activity_groups($courseid, $userid);
        $detail->sessions = $sessions;
        $detail->progress = $this->get_progress($sessions);

        // The one session that can be joined right now, if any. Only a session
        // whose BigBlueButton room exists can be joined: a button that opens
        // nothing is worse than no button.
        $detail->livesession = null;
        foreach ($sessions as $session) {
            if ($session->islive && $session->joinurl) {
                $detail->livesession = $session;
                break;
            }
        }

        return $detail;
    }

    /**
     * Who Moodle records as teaching this course.
     *
     * Read from role assignments, not from local_pshub_teacher_assign - that
     * table holds the ADMIN'S BRIEF to a teacher (which competencies a course
     * must cover), which is a different question, and would name a teacher who
     * was asked to build a course rather than one who teaches it.
     *
     * @param int $courseid
     * @return stdClass[] [{fullname, initials}]
     */
    private function get_teachers(int $courseid): array {
        global $CFG;

        // local_pshub_user_initials() lives in the plugin's lib.php, which
        // Moodle loads on its own for some entry points and not for others.
        // PHPUnit loads every plugin lib.php, so no test can catch this: the
        // page died on a course that actually had a teacher, while the test
        // covering this method passed.
        require_once($CFG->dirroot . '/local/pshub/lib.php');

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        // Every name field fullname() might read, chosen by core rather than
        // guessed here - a hand-written list makes fullname() complain and
        // prints a developer notice across the top of the page.
        $fields = \core_user\fields::for_name()->get_required_fields();

        $users = get_enrolled_users($context, 'moodle/course:manageactivities', 0,
            'u.id, u.' . implode(', u.', $fields), 'u.lastname ASC', 0, 20);

        $teachers = [];
        foreach ($users as $user) {
            $teachers[] = (object) [
                'fullname' => fullname($user),
                'initials' => \local_pshub_user_initials($user),
            ];
        }

        return $teachers;
    }

    /**
     * The course's activities, grouped the way the learner's page shows them.
     *
     * Only groups that have something in them come back. The approved screen
     * has tabs for resources, quizzes, assignments and workshops, but a tab
     * that opens onto nothing is worse than a tab that is not there - so the
     * tabs are built from what the course contains, not from the design.
     *
     * @param int $courseid
     * @param int $userid Whose view of the course this is.
     * @return stdClass[] [{key, label, count, items: [{name, url, hasurl}]}]
     */
    private function get_activity_groups(int $courseid, int $userid): array {
        $map = [
            'resources' => ['resource', 'url', 'page', 'folder', 'book'],
            'quizzes' => ['quiz'],
            'assignments' => ['assign'],
            'workshops' => ['workshop'],
            'discussions' => ['forum'],
        ];

        // For THIS learner, not for whoever is signed in: uservisible is
        // answered against the user modinfo was built for, and a service that
        // takes a userid must not quietly answer about somebody else.
        $modinfo = get_fast_modinfo($courseid, $userid);
        $found = [];

        foreach ($modinfo->get_cms() as $cm) {
            // uservisible is the one honest test: it covers hidden activities,
            // availability restrictions and group membership at once.
            if (!$cm->uservisible) {
                continue;
            }
            foreach ($map as $key => $modnames) {
                if (in_array($cm->modname, $modnames, true)) {
                    $found[$key][] = (object) [
                        'name' => $cm->get_formatted_name(),
                        'url' => $cm->url ? $cm->url->out(false) : '',
                        'hasurl' => (bool) $cm->url,
                    ];
                    break;
                }
            }
        }

        $groups = [];
        foreach (array_keys($map) as $key) {
            if (empty($found[$key])) {
                continue;
            }
            $groups[] = (object) [
                'key' => $key,
                'label' => get_string('learn_group_' . $key, 'local_pshub'),
                'count' => count($found[$key]),
                'items' => $found[$key],
            ];
        }

        return $groups;
    }

    /**
     * Every session on one course, with what this learner did about it.
     *
     * Cancelled sessions are left out: a class that will not happen is not
     * part of the course the learner is looking at. Attendance is read from
     * counted_present, the same flag that drives XP (AC-14), so the page and
     * the points can never disagree.
     *
     * @param int $userid
     * @param int $courseid
     * @return stdClass[] Session rows plus attended, islive, ispast, joinurl.
     */
    private function get_course_sessions(int $userid, int $courseid): array {
        global $DB;

        $sql = "SELECT s.id, s.title, s.scheduled_start, s.scheduled_end,
                       s.duration_minutes, s.status, s.bbb_cmid,
                       a.counted_present
                  FROM {local_pshub_session} s
             LEFT JOIN {local_pshub_attendance} a
                       ON a.sessionid = s.id AND a.userid = :userid
                 WHERE s.courseid = :courseid AND s.status <> :cancelled
              ORDER BY s.scheduled_start ASC, s.id ASC";

        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'cancelled' => constants::SESSION_CANCELLED,
        ]);

        $now = time();
        $sessions = [];

        foreach ($records as $record) {
            $record->attended = !empty($record->counted_present);
            $record->islive = ($record->status === constants::SESSION_LIVE);
            $record->ispast = ($record->status === constants::SESSION_ENDED
                || $record->scheduled_end < $now);
            // The room is a BigBlueButton activity in the course. No room, no
            // link - the session is still real, it just cannot be entered yet.
            $record->joinurl = $record->bbb_cmid
                ? (new \moodle_url('/mod/bigbluebuttonbn/view.php',
                    ['id' => $record->bbb_cmid]))->out(false)
                : null;
            $sessions[] = $record;
        }

        return $sessions;
    }

    /**
     * How far through the course this learner is, in sessions attended.
     *
     * Sessions, not activities, because this platform teaches live: the course
     * IS its sessions (§1). Returns false when none are scheduled yet - zero
     * out of zero is not a beginning, and a bar that is empty because nothing
     * exists reads as failure.
     *
     * @param stdClass[] $sessions As returned by get_course_sessions().
     * @return stdClass|false attended, total, percent
     */
    private function get_progress(array $sessions) {
        $total = count($sessions);
        if (!$total) {
            return false;
        }

        $attended = 0;
        foreach ($sessions as $session) {
            if ($session->attended) {
                $attended++;
            }
        }

        return (object) [
            'attended' => $attended,
            'total' => $total,
            'percent' => (int) round($attended / $total * 100),
        ];
    }

    /**
     * Total points earned.
     *
     * Summed from the ledger rather than kept as a running total, so a
     * correction to the ledger is a correction to the figure. Zero is a real
     * answer and is shown as one.
     *
     * @param int $userid
     * @return int
     */
    public function get_xp(int $userid): int {
        global $DB;

        $sum = $DB->get_field_sql(
            'SELECT SUM(points) FROM {local_pshub_xp_ledger} WHERE userid = :userid',
            ['userid' => $userid]
        );

        return (int) $sum;
    }
}
