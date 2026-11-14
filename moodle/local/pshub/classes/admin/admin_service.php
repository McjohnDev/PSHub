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

namespace local_pshub\admin;

use local_pshub\local\constants;
use stdClass;

/**
 * What the administration console knows about the platform.
 *
 * Read only. Every figure here is counted from the tables that hold the thing
 * being counted - courses waiting for review from the course table, money from
 * the transaction table - so the console cannot disagree with the pages that
 * do the work. It changes nothing: approving a course is sprint 4, validating
 * a payment sprint 7, and both go through their own services with their own
 * audit rows (§3.4, §3.8).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_service {

    /**
     * How many hub courses sit in each review state.
     *
     * @return array state => count, for every state, including the ones at zero
     */
    public function get_course_counts(): array {
        global $DB;

        $counts = [];
        foreach ([constants::REVIEW_DRAFT, constants::REVIEW_PENDING, constants::REVIEW_PUBLISHED,
                  constants::REVIEW_REJECTED, constants::REVIEW_ARCHIVED] as $state) {
            $counts[$state] = $DB->count_records('local_pshub_course', ['review_status' => $state]);
        }

        return $counts;
    }

    /**
     * The courses waiting for a decision, oldest first.
     *
     * Oldest first on purpose: a queue ordered by newest leaves the course
     * that has waited longest at the bottom, which is how a teacher ends up
     * waiting a fortnight.
     *
     * @param int $limit
     * @return stdClass[] {courseid, fullname, langplate, cardtone, submitter,
     *                     submitted, url}
     */
    public function get_review_queue(int $limit = 10): array {
        global $DB;

        $sql = "SELECT p.courseid, p.lang_code, p.timesubmitted, p.submittedby, c.fullname
                  FROM {local_pshub_course} p
                  JOIN {course} c ON c.id = p.courseid
                 WHERE p.review_status = :pending
              ORDER BY p.timesubmitted ASC, c.fullname ASC";

        $rows = [];

        foreach ($DB->get_records_sql($sql, ['pending' => constants::REVIEW_PENDING], 0, $limit) as $record) {
            $out = new stdClass();
            $out->courseid = (int) $record->courseid;
            $out->fullname = format_string($record->fullname);
            $out->langplate = \core_text::strtoupper($record->lang_code ?? '');
            $out->cardtone = ((int) $record->courseid % 4) + 1;
            $out->timesubmitted = (int) $record->timesubmitted;

            // Who submitted it, when that is recorded. A course seeded before
            // the submission flow existed has no submitter, and inventing one
            // would put a name next to a decision they never made.
            $out->submitter = null;
            if ($record->submittedby) {
                // Every name field, not the two obvious ones: fullname() also
                // reads phonetic, middle and alternate names, and warns when
                // handed a record that was selected by hand.
                $fields = \core_user\fields::for_name()->get_required_fields();
                $user = \core_user::get_user($record->submittedby, 'id, ' . implode(', ', $fields));
                $out->submitter = $user ? fullname($user) : null;
            }
            $out->hassubmitter = (bool) $out->submitter;
            $out->url = (new \moodle_url('/course/view.php', ['id' => $record->courseid]))->out(false);

            $rows[] = $out;
        }

        return $rows;
    }

    /**
     * Money, as the transaction table records it.
     *
     * Returns false when there is not a single transaction: a console that
     * prints "0 FCFA, +0 % on last month" on a platform that has never taken a
     * payment is reporting on something that does not exist yet. The figures
     * appear when the first payment does.
     *
     * @return stdClass|false validated (minor units), currency, pending (count),
     *         pendingamount
     */
    public function get_money() {
        global $DB;

        if (!$DB->record_exists('local_pshub_transaction', [])) {
            return false;
        }

        $money = new stdClass();
        $money->currency = 'XAF';

        $money->validated = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(amount), 0) FROM {local_pshub_transaction} WHERE status = :status",
            ['status' => constants::TXN_VALIDATED]);

        $money->pending = $DB->count_records('local_pshub_transaction',
            ['status' => constants::TXN_PAID_UNVALIDATED]);

        $money->pendingamount = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(amount), 0) FROM {local_pshub_transaction} WHERE status = :status",
            ['status' => constants::TXN_PAID_UNVALIDATED]);

        return $money;
    }

    /**
     * How many people hold a teaching role, and how many are learning.
     *
     * A teacher is counted by capability, never by local_pshub_user_profile
     * .platform_role: that column is display only (§5.3). A learner is somebody
     * enrolled in a hub course who is not staff there - the same definition the
     * teacher's console uses, so the two cannot disagree.
     *
     * @return stdClass teachers, learners
     */
    public function get_people(): stdClass {
        global $DB;

        $out = new stdClass();

        $out->teachers = count(get_users_by_capability(\context_system::instance(),
            'local/pshub:viewteacherportal', 'u.id'));

        $learners = [];
        foreach ($DB->get_fieldset_select('local_pshub_course', 'courseid', '') as $courseid) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            foreach (get_enrolled_users($context, '', 0, 'u.id') as $user) {
                if (!has_capability('moodle/course:manageactivities', $context, $user->id)) {
                    $learners[$user->id] = true;
                }
            }
        }
        $out->learners = count($learners);

        return $out;
    }

    /**
     * The course types an administrator has defined, with their badges.
     *
     * @return stdClass[]
     */
    public function get_types(): array {
        global $DB;

        return array_values($DB->get_records('local_pshub_course_type', null, 'sortorder ASC, id ASC'));
    }
}
