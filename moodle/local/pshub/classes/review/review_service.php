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

namespace local_pshub\review;

use local_pshub\audit\audit_service;
use local_pshub\local\constants;
use stdClass;

/**
 * The course validation workflow: the one place a course changes state.
 *
 * THREE RULES THIS CLASS EXISTS TO ENFORCE
 *
 * 1. **`review_status` and `course.visible` are the same fact** (§3.4, AC-1).
 *    Nothing else in this plugin writes `course.visible` for a hub course. A
 *    course is visible in Moodle exactly when the hub has published it, so a
 *    draft cannot be reachable by guessing its URL.
 * 2. **A rejection carries its reason.** reject() refuses an empty comment,
 *    because a teacher told only "no" has been told nothing.
 * 3. **Every transition is audited, inside the same transaction.** A committed
 *    change always has its audit row; a rolled back one leaves nothing behind.
 *
 * A teacher cannot publish, including their own course: `publishcourse`
 * declares only the manager archetype, so an editingteacher-derived role never
 * receives it (AC-1). This class checks the capability anyway - a service that
 * trusts its caller is one page away from being called by the wrong one.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_service {

    /** @var audit_service */
    private audit_service $audit;

    /**
     * @param audit_service|null $audit
     */
    public function __construct(?audit_service $audit = null) {
        $this->audit = $audit ?? new audit_service();
    }

    /**
     * Why this course cannot be submitted yet, as a list of reasons.
     *
     * An empty list means it can. The checks are the ones §3.4 names, and each
     * is a thing a learner would otherwise meet on the catalogue: a course
     * with no summary, no language, no level, or a price of nothing that is
     * not marked free.
     *
     * The competency check stays out until S15, as the backlog says: there is
     * no framework to check against yet, and a check that always passes is
     * worse than one that is openly absent.
     *
     * @param int $courseid
     * @return string[] Human-readable reasons, empty when ready.
     */
    public function submission_problems(int $courseid): array {
        global $DB;

        $hub = $DB->get_record('local_pshub_course', ['courseid' => $courseid]);
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, summary');

        if (!$hub || !$course) {
            return [get_string('review_problem_notahubcourse', 'local_pshub')];
        }

        $problems = [];

        if (trim(strip_tags($course->summary ?? '')) === '') {
            $problems[] = get_string('review_problem_nosummary', 'local_pshub');
        }
        if (empty($hub->lang_code)) {
            $problems[] = get_string('review_problem_nolanguage', 'local_pshub');
        }
        if (empty($hub->cefr_level)) {
            $problems[] = get_string('review_problem_nolevel', 'local_pshub');
        }
        if ($hub->access_type === constants::ACCESS_TYPE_PAID && (int) $hub->price_amount <= 0) {
            $problems[] = get_string('review_problem_noprice', 'local_pshub');
        }

        return $problems;
    }

    /**
     * Send a course for validation.
     *
     * From draft or rejected only. A pending course is already in the queue
     * and a published one is not a draft; neither is an error worth a stack
     * trace, so both are refused with a message the teacher can act on.
     *
     * @param int $courseid
     * @param int|null $actorid Null means the current user.
     * @return void
     * @throws \moodle_exception
     */
    public function submit(int $courseid, ?int $actorid = null): void {
        global $DB, $USER;

        $actorid = $actorid ?? (int) $USER->id;
        $hub = $this->hub_course($courseid);

        $context = \context_course::instance($courseid);
        require_capability('local/pshub:submitcourse', $context);

        if (!in_array($hub->review_status, [constants::REVIEW_DRAFT, constants::REVIEW_REJECTED], true)) {
            throw new \moodle_exception('review_cannot_submit', 'local_pshub');
        }

        $problems = $this->submission_problems($courseid);
        if ($problems) {
            throw new \moodle_exception('review_incomplete', 'local_pshub', '', implode(' ', $problems));
        }

        $transaction = $DB->start_delegated_transaction();

        $before = $this->state($hub);

        $hub->review_status = constants::REVIEW_PENDING;
        $hub->submittedby = $actorid;
        $hub->timesubmitted = time();
        $hub->timemodified = time();
        $DB->update_record('local_pshub_course', $hub);

        // A course waiting for review is not published, so it is not visible.
        $this->apply_visibility($courseid, constants::REVIEW_PENDING);

        $this->audit->record(audit_service::ACTION_COURSE_SUBMITTED, audit_service::TARGET_COURSE,
            $courseid, $before, $this->state($hub), $actorid);

        $transaction->allow_commit();

        $this->notify_reviewers($courseid);
    }

    /**
     * Approve a course: it is published, and it becomes visible.
     *
     * @param int $courseid
     * @param int|null $actorid
     * @return void
     * @throws \moodle_exception
     */
    public function approve(int $courseid, ?int $actorid = null): void {
        global $DB, $USER;

        $actorid = $actorid ?? (int) $USER->id;
        $hub = $this->hub_course($courseid);

        require_capability('local/pshub:publishcourse', \context_system::instance());

        if ($hub->review_status !== constants::REVIEW_PENDING) {
            throw new \moodle_exception('review_not_pending', 'local_pshub');
        }

        $transaction = $DB->start_delegated_transaction();

        $before = $this->state($hub);

        $hub->review_status = constants::REVIEW_PUBLISHED;
        $hub->reviewedby = $actorid;
        $hub->timereviewed = time();
        $hub->review_comment = null;
        $hub->timemodified = time();
        $DB->update_record('local_pshub_course', $hub);

        $this->apply_visibility($courseid, constants::REVIEW_PUBLISHED);

        $this->audit->record(audit_service::ACTION_COURSE_PUBLISHED, audit_service::TARGET_COURSE,
            $courseid, $before, $this->state($hub), $actorid);

        $transaction->allow_commit();

        $this->notify_author($courseid, $hub, true);
    }

    /**
     * Reject a course, with the reason that makes the rejection useful.
     *
     * @param int $courseid
     * @param string $comment Required. Refused when empty.
     * @param int|null $actorid
     * @return void
     * @throws \moodle_exception
     */
    public function reject(int $courseid, string $comment, ?int $actorid = null): void {
        global $DB, $USER;

        $actorid = $actorid ?? (int) $USER->id;
        $hub = $this->hub_course($courseid);

        require_capability('local/pshub:publishcourse', \context_system::instance());

        if ($hub->review_status !== constants::REVIEW_PENDING) {
            throw new \moodle_exception('review_not_pending', 'local_pshub');
        }

        // The one check that is a product rule rather than a data rule: a
        // teacher told only "no" has been told nothing (§3.4, AC-1).
        if (trim($comment) === '') {
            throw new \moodle_exception('review_comment_required', 'local_pshub');
        }

        $transaction = $DB->start_delegated_transaction();

        $before = $this->state($hub);

        $hub->review_status = constants::REVIEW_REJECTED;
        $hub->reviewedby = $actorid;
        $hub->timereviewed = time();
        $hub->review_comment = trim($comment);
        $hub->timemodified = time();
        $DB->update_record('local_pshub_course', $hub);

        $this->apply_visibility($courseid, constants::REVIEW_REJECTED);

        $this->audit->record(audit_service::ACTION_COURSE_REJECTED, audit_service::TARGET_COURSE,
            $courseid, $before, $this->state($hub), $actorid);

        $transaction->allow_commit();

        $this->notify_author($courseid, $hub, false);
    }

    /**
     * The single place `course.visible` is written for a hub course.
     *
     * Published means visible; everything else means hidden. Nothing else in
     * this plugin touches the column, which is what makes the two facts
     * impossible to disagree (§3.4).
     *
     * @param int $courseid
     * @param string $status
     * @return void
     */
    private function apply_visibility(int $courseid, string $status): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $visible = ($status === constants::REVIEW_PUBLISHED) ? 1 : 0;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, visible', MUST_EXIST);

        if ((int) $course->visible === $visible) {
            return;
        }

        // Core's own helpers, so that the change goes through the same events,
        // cache invalidation and child-activity handling as any other
        // show/hide in Moodle.
        if ($visible) {
            course_change_visibility($courseid, true);
        } else {
            course_change_visibility($courseid, false);
        }
    }

    /**
     * Tell whoever reviews courses that one is waiting.
     *
     * Moodle's message API, so it lands wherever the recipient has said they
     * want it - in-app, e-mail, mobile - instead of this plugin deciding for
     * them (§10.1).
     *
     * @param int $courseid
     * @return void
     */
    private function notify_reviewers(int $courseid): void {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
        // The whole record: message_send() reads more of a user than any
        // hand-written list remembers, and the name fields are site-configurable.
        $reviewers = get_users_by_capability(\context_system::instance(),
            'local/pshub:publishcourse', 'u.*');

        foreach ($reviewers as $reviewer) {
            $this->send($reviewer, 'coursesubmitted',
                $this->in_their_language($reviewer, 'notify_submitted_subject'),
                $this->in_their_language($reviewer, 'notify_submitted_body',
                    format_string($course->fullname)),
                new \moodle_url('/local/pshub/admin/index.php'));
        }
    }

    /**
     * Tell the teacher what was decided about their course.
     *
     * @param int $courseid
     * @param stdClass $hub
     * @param bool $approved
     * @return void
     */
    private function notify_author(int $courseid, stdClass $hub, bool $approved): void {
        global $DB;

        if (empty($hub->submittedby)) {
            return;
        }

        $author = \core_user::get_user($hub->submittedby);
        if (!$author) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
        $name = format_string($course->fullname);

        if ($approved) {
            $subject = $this->in_their_language($author, 'notify_approved_subject');
            $body = $this->in_their_language($author, 'notify_approved_body', $name);
        } else {
            $subject = $this->in_their_language($author, 'notify_rejected_subject');
            $body = $this->in_their_language($author, 'notify_rejected_body',
                (object) ['course' => $name, 'comment' => $hub->review_comment]);
        }

        $this->send($author, 'coursereviewed', $subject, $body,
            new \moodle_url('/local/pshub/teacher/index.php'));
    }

    /**
     * A string in the RECIPIENT's language, not the sender's.
     *
     * get_string() answers in the language of whoever is logged in, so an
     * administrator working in English was sending English notifications to
     * French teachers - on a product whose default language is French. The
     * string manager takes the language explicitly; this is the only reliable
     * way to do it without changing the current language mid-request.
     *
     * @param stdClass $user The recipient.
     * @param string $id String identifier.
     * @param mixed $a
     * @return string
     */
    private function in_their_language(stdClass $user, string $id, $a = null): string {
        return get_string_manager()->get_string($id, 'local_pshub', $a, $user->lang ?? null);
    }

    /**
     * One notification.
     *
     * @param stdClass $to
     * @param string $name Message provider name.
     * @param string $subject
     * @param string $body
     * @param \moodle_url $url
     * @return void
     */
    private function send(stdClass $to, string $name, string $subject, string $body,
            \moodle_url $url): void {
        $message = new \core\message\message();
        $message->component = 'local_pshub';
        $message->name = $name;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $to;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $subject;

        message_send($message);
    }

    /**
     * The hub row for a course, or a clear failure.
     *
     * @param int $courseid
     * @return stdClass
     * @throws \moodle_exception
     */
    private function hub_course(int $courseid): stdClass {
        global $DB;

        $hub = $DB->get_record('local_pshub_course', ['courseid' => $courseid]);
        if (!$hub) {
            throw new \moodle_exception('review_problem_notahubcourse', 'local_pshub');
        }

        return $hub;
    }

    /**
     * What goes in the audit trail's before and after.
     *
     * The review fields only: an audit row is a record of a decision, not a
     * copy of the whole course.
     *
     * @param stdClass $hub
     * @return array
     */
    private function state(stdClass $hub): array {
        return [
            'review_status' => $hub->review_status,
            'submittedby' => (int) ($hub->submittedby ?? 0),
            'reviewedby' => (int) ($hub->reviewedby ?? 0),
            'review_comment' => $hub->review_comment,
        ];
    }
}
