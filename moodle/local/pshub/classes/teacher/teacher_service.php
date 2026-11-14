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

namespace local_pshub\teacher;

use local_pshub\local\constants;
use stdClass;

/**
 * What a teacher's console knows about their own work.
 *
 * Everything here is read from data that exists: the courses they teach, the
 * review status of each, how many learners are in them, the sessions they are
 * down to run, and the brief an administrator wrote for them. Where the
 * approved screen shows something the platform does not record - an average
 * rating - this returns nothing and the page leaves the space out.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_service {

    /**
     * The hub courses this person teaches.
     *
     * Teaching is read as the capability to manage a course's activities, the
     * same test the learner's course page uses to name a teacher - so the two
     * pages can never disagree about who teaches what. It also catches a role
     * assigned at category level, which a query on course role assignments
     * would miss.
     *
     * Drafts and rejected courses are included: this is the teacher's own
     * workbench, and a course that was rejected is exactly what they need to
     * see.
     *
     * @param int $teacherid
     * @return stdClass[] {courseid, fullname, status, statuslabel, langplate,
     *                     cardtone, learners, url, reviewcomment}
     */
    public function get_courses(int $teacherid): array {
        global $DB;

        $sql = "SELECT p.courseid, p.lang_code, p.review_status, p.review_comment,
                       p.timesubmitted, c.fullname
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
              ORDER BY c.fullname ASC";

        $courses = [];

        foreach ($DB->get_records_sql($sql) as $record) {
            $context = \context_course::instance($record->courseid, IGNORE_MISSING);
            if (!$context || !has_capability('moodle/course:manageactivities', $context, $teacherid)) {
                continue;
            }

            $out = new stdClass();
            $out->courseid = (int) $record->courseid;
            $out->fullname = format_string($record->fullname);
            $out->status = $record->review_status;
            $out->statuslabel = get_string('status_' . $record->review_status, 'local_pshub');
            $out->langplate = \core_text::strtoupper($record->lang_code ?? '');
            $out->cardtone = ((int) $record->courseid % 4) + 1;
            $out->learners = $this->count_learners($context);
            // French puts both zero and one in the singular, so the label is
            // chosen here: a template cannot count, and "1 apprenant·e·s" is a
            // mistake a reader sees before anything else on the row.
            $out->learnerlabel = ($out->learners <= 1)
                ? get_string('teacher_learner_one', 'local_pshub', $out->learners)
                : get_string('teacher_learner_count', 'local_pshub', $out->learners);
            $out->url = (new \moodle_url('/course/view.php', ['id' => $record->courseid]))->out(false);

            // Submitting is offered on the two states it is allowed from. The
            // service checks this again - the button is a convenience, not the
            // rule.
            $out->cansubmit = in_array($record->review_status,
                [constants::REVIEW_DRAFT, constants::REVIEW_REJECTED], true);
            $out->submiturl = (new \moodle_url('/local/pshub/review.php',
                ['id' => $record->courseid, 'action' => 'submit']))->out(false);

            // Editing is allowed in the same two states. Without this link a
            // course rejected for a missing level is a dead end: the teacher
            // was told what to fix and given nowhere to fix it.
            // The diary is reachable whatever the review status: a published
            // course is precisely the one that needs dates.
            $out->sessionsurl = (new \moodle_url('/local/pshub/teacher/sessions.php',
                ['courseid' => $record->courseid]))->out(false);

            $out->canedit = $out->cansubmit;
            $out->editurl = (new \moodle_url('/local/pshub/teacher/course.php',
                ['id' => $record->courseid]))->out(false);

            // A rejection without its reason is just a closed door. The comment
            // is required on reject (§3.4), so when the status is rejected
            // there is always one to show.
            $out->hascomment = ($record->review_status === constants::REVIEW_REJECTED
                && !empty($record->review_comment));
            $out->reviewcomment = $out->hascomment ? format_text($record->review_comment, FORMAT_PLAIN) : null;

            $courses[] = $out;
        }

        return $courses;
    }

    /**
     * How many learners are in a course.
     *
     * Everyone enrolled, minus the people who can manage its activities. There
     * is no "learner" flag to count: a learner is someone enrolled who is not
     * staff, which is the same definition the rest of the platform uses.
     *
     * @param \context_course $context
     * @return int
     */
    private function count_learners(\context_course $context): int {
        $all = count_enrolled_users($context);
        $staff = count_enrolled_users($context, 'moodle/course:manageactivities');

        return max(0, $all - $staff);
    }

    /**
     * The sessions this teacher is down to run, soonest first.
     *
     * Only ones still to come, and never a cancelled class: that is not an
     * appointment.
     *
     * @param int $teacherid
     * @param int $limit
     * @return stdClass[]
     */
    public function get_upcoming_sessions(int $teacherid, int $limit = 6): array {
        global $DB;

        $sql = "SELECT s.id, s.courseid, s.title, s.scheduled_start, s.duration_minutes,
                       s.status, c.fullname AS coursename
                  FROM {local_pshub_session} s
                  JOIN {course} c ON c.id = s.courseid
                 WHERE s.teacherid = :teacherid
                       AND s.scheduled_end >= :now
                       AND s.status IN (:scheduled, :live)
              ORDER BY s.scheduled_start ASC";

        return array_values($DB->get_records_sql($sql, [
            'teacherid' => $teacherid,
            'now' => time(),
            'scheduled' => constants::SESSION_SCHEDULED,
            'live' => constants::SESSION_LIVE,
        ], 0, $limit));
    }

    /**
     * The briefs an administrator wrote for this teacher.
     *
     * This is local_pshub_teacher_assign: which competencies a course they
     * build must cover (§3.4). It is the one thing on the console that comes
     * from the administration rather than from their own work, and a teacher
     * is expected to have read it before submitting anything.
     *
     * @param int $teacherid
     * @return stdClass[]
     */
    public function get_briefs(int $teacherid): array {
        global $DB;

        $sql = "SELECT a.id, a.brief, a.assigned_at, a.courseid, c.fullname AS coursename
                  FROM {local_pshub_teacher_assign} a
             LEFT JOIN {course} c ON c.id = a.courseid
                 WHERE a.teacherid = :teacherid
              ORDER BY a.assigned_at DESC";

        return array_values($DB->get_records_sql($sql, ['teacherid' => $teacherid]));
    }

    /**
     * The three figures at the top of the console.
     *
     * Not four: the approved screen shows an average rating, and nothing on
     * this platform records a review. A console that prints an invented 4,8
     * beside real numbers makes the real ones suspect too.
     *
     * @param stdClass[] $courses As returned by get_courses().
     * @return stdClass published, pending, learners
     */
    public function get_totals(array $courses): stdClass {
        $totals = (object) ['published' => 0, 'pending' => 0, 'learners' => 0];

        foreach ($courses as $course) {
            if ($course->status === constants::REVIEW_PUBLISHED) {
                $totals->published++;
            }
            if ($course->status === constants::REVIEW_PENDING) {
                $totals->pending++;
            }
            $totals->learners += $course->learners;
        }

        return $totals;
    }
}
