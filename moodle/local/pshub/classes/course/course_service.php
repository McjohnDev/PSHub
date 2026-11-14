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

namespace local_pshub\course;

use local_pshub\audit\audit_service;
use local_pshub\local\constants;
use stdClass;

/**
 * Creating and editing a hub course.
 *
 * A course created through Moodle's own form is a Moodle course and nothing
 * more: no language, no level, no price, no review status, and so no place in
 * the catalogue. This service creates both halves at once - the Moodle course
 * that holds the teaching, and the row that makes it part of the product -
 * so the two cannot exist apart (§3.4).
 *
 * WHO MAY CHANGE WHAT
 * A teacher edits a course while it is theirs to work on: draft, or rejected
 * and waiting to be fixed. A course under review is frozen, because a reviewer
 * reading it should not have it change underneath them, and a published course
 * is frozen to teachers because its price and level are what the catalogue
 * sold. Whoever may publish may edit at any time.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_service {

    /** @var audit_service */
    private audit_service $audit;

    /**
     * @param audit_service|null $audit
     */
    public function __construct(?audit_service $audit = null) {
        $this->audit = $audit ?? new audit_service();
    }

    /**
     * Create a hub course: a hidden Moodle course and its catalogue row.
     *
     * Hidden, because `review_status` and `course.visible` are the same fact
     * and a new course is a draft. Only review_service makes a course visible.
     *
     * @param stdClass $data From course_form.
     * @param int|null $teacherid Who will teach it. Null means the current user.
     * @return int The new course id.
     */
    public function create(stdClass $data, ?int $teacherid = null): int {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        $teacherid = $teacherid ?? (int) $USER->id;

        $transaction = $DB->start_delegated_transaction();

        $course = new stdClass();
        $course->fullname = $data->fullname;
        $course->shortname = $this->unique_shortname($data->fullname);
        $course->category = $this->category_id();
        $course->summary = $data->summary ?? '';
        $course->summaryformat = FORMAT_HTML;
        // A draft is not visible. Nothing else here decides that.
        $course->visible = 0;

        $course = create_course($course);

        $hub = new stdClass();
        $hub->courseid = $course->id;
        $hub->categoryid = $course->category;
        $hub->review_status = constants::REVIEW_DRAFT;
        $hub->is_featured = 0;
        $hub->featured_order = 0;
        $hub->timecreated = time();
        $hub->timemodified = time();
        $this->apply_metadata($hub, $data);

        $DB->insert_record('local_pshub_course', $hub);

        // Whoever created it teaches it, through Moodle's own enrolment - the
        // same path that makes them a teacher anywhere else, so the learner's
        // course page and the teacher's console agree about who teaches what.
        $this->enrol_teacher($course->id, $teacherid);

        $this->audit->record(audit_service::ACTION_COURSE_CREATED, audit_service::TARGET_COURSE,
            $course->id, null, ['review_status' => constants::REVIEW_DRAFT], $teacherid);

        $transaction->allow_commit();

        return (int) $course->id;
    }

    /**
     * Change a hub course's catalogue metadata and its Moodle name and summary.
     *
     * @param int $courseid
     * @param stdClass $data From course_form.
     * @return void
     * @throws \moodle_exception
     */
    public function update(int $courseid, stdClass $data): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $hub = $DB->get_record('local_pshub_course', ['courseid' => $courseid], '*', MUST_EXIST);

        if (!$this->can_edit($hub)) {
            throw new \moodle_exception('course_locked', 'local_pshub');
        }

        $transaction = $DB->start_delegated_transaction();

        $before = $this->metadata_state($hub);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $course->fullname = $data->fullname;
        $course->summary = $data->summary ?? '';
        $course->summaryformat = FORMAT_HTML;
        update_course($course);

        $hub->timemodified = time();
        $this->apply_metadata($hub, $data);
        $DB->update_record('local_pshub_course', $hub);

        $this->audit->record(audit_service::ACTION_COURSE_UPDATED, audit_service::TARGET_COURSE,
            $courseid, $before, $this->metadata_state($hub));

        $transaction->allow_commit();
    }

    /**
     * Whether this course may be edited right now, by this user.
     *
     * @param stdClass $hub The local_pshub_course row.
     * @return bool
     */
    public function can_edit(stdClass $hub): bool {
        if (has_capability('local/pshub:publishcourse', \context_system::instance())) {
            return true;
        }

        return in_array($hub->review_status,
            [constants::REVIEW_DRAFT, constants::REVIEW_REJECTED], true);
    }

    /**
     * Copy the form's catalogue fields onto a hub row.
     *
     * One place, so create and update cannot drift apart.
     *
     * @param stdClass $hub Modified in place.
     * @param stdClass $data
     * @return void
     */
    private function apply_metadata(stdClass $hub, stdClass $data): void {
        $hub->lang_code = $data->lang_code;
        $hub->cefr_level = $data->cefr_level;
        $hub->session_duration_minutes = (int) $data->session_duration_minutes;
        $hub->is_one_to_one = !empty($data->is_one_to_one) ? 1 : 0;
        $hub->audience = $data->audience ?? 'adult';

        // One to one has no group size. Leaving a stale number behind would
        // put "12 learners per group maximum" on a course for one person.
        $hub->max_participants = $hub->is_one_to_one
            ? null
            : (!empty($data->max_participants) ? (int) $data->max_participants : null);

        $hub->access_type = $data->access_type;
        $hub->price_amount = ($data->access_type === constants::ACCESS_TYPE_PAID)
            ? (int) $data->price_amount
            : 0;
        $hub->price_currency = $data->price_currency ?? 'XAF';
    }

    /**
     * The catalogue fields, for the audit trail.
     *
     * @param stdClass $hub
     * @return array
     */
    private function metadata_state(stdClass $hub): array {
        return [
            'lang_code' => $hub->lang_code,
            'cefr_level' => $hub->cefr_level,
            'access_type' => $hub->access_type,
            'price_amount' => (int) $hub->price_amount,
            'session_duration_minutes' => (int) $hub->session_duration_minutes,
            'is_one_to_one' => (int) $hub->is_one_to_one,
            'max_participants' => $hub->max_participants,
        ];
    }

    /**
     * Enrol somebody as the course's teacher.
     *
     * @param int $courseid
     * @param int $teacherid
     * @return void
     */
    private function enrol_teacher(int $courseid, int $teacherid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/enrollib.php');

        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id', IGNORE_MISSING);
        if (!$role) {
            return;
        }

        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MISSING);

        if (!$instance) {
            // Site defaults can leave a new course without one, and returning
            // here would hand back a course with no teacher - which the
            // teacher's own console would then not list.
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            enrol_get_plugin('manual')->add_instance($course);
            $instance = $DB->get_record('enrol',
                ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
        }

        enrol_get_plugin('manual')->enrol_user($instance, $teacherid, $role->id);
    }

    /**
     * A shortname Moodle will accept, derived from the title.
     *
     * Moodle requires one and requires it to be unique; a teacher should not
     * have to invent an identifier, nor meet "shortname already taken" for a
     * course someone else named similarly.
     *
     * @param string $fullname
     * @return string
     */
    private function unique_shortname(string $fullname): string {
        global $DB;

        $base = \core_text::strtolower(trim($fullname));
        $base = preg_replace('/[^a-z0-9]+/', '-', \core_text::specialtoascii($base));
        $base = trim($base, '-');
        $base = $base === '' ? 'cours' : \core_text::substr($base, 0, 60);

        $candidate = $base;
        $suffix = 2;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Which Moodle category hub courses are created in.
     *
     * The admin setting when one is chosen, and otherwise the first category
     * that exists - a new site has exactly one, and refusing to create a course
     * until somebody picks it would be a wall in front of the first teacher.
     *
     * @return int
     */
    private function category_id(): int {
        global $DB;

        $configured = (int) get_config('local_pshub', 'categoryid');
        if ($configured && $DB->record_exists('course_categories', ['id' => $configured])) {
            return $configured;
        }

        return (int) $DB->get_field_sql(
            'SELECT MIN(id) FROM {course_categories} WHERE visible = 1');
    }
}
