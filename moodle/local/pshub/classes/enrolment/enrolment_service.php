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

namespace local_pshub\enrolment;

use local_pshub\access\access_service;
use local_pshub\access\gating_result;
use local_pshub\access\gating_service;
use local_pshub\local\constants;

/**
 * Putting a learner into a course.
 *
 * An enrolment and an access right must exist together or not at all. A
 * learner enrolled with no right sees a paywall on a course listed under "my
 * courses"; a learner with a right but no enrolment has paid for a course
 * Moodle will not let them open. Both are support tickets, and both are
 * avoided by never writing one without the other (AC-9).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_service {

    /** @var gating_service */
    private gating_service $gating;

    /** @var access_service */
    private access_service $access;

    /**
     * @param gating_service|null $gating
     * @param access_service|null $access
     */
    public function __construct(?gating_service $gating = null, ?access_service $access = null) {
        $this->gating = $gating ?? new gating_service();
        $this->access = $access ?? new access_service();
    }

    /**
     * Enrol a learner in a free course.
     *
     * @param int $userid
     * @param int $courseid
     * @return gating_result The verdict. A denial means nothing was written.
     */
    public function enrol_free(int $userid, int $courseid): gating_result {
        global $DB;

        $verdict = $this->gating->check($userid, $courseid);
        if (!$verdict->is_granted()) {
            return $verdict;
        }

        $transaction = $DB->start_delegated_transaction();
        $this->access->grant(
            $userid,
            'course',
            $courseid,
            constants::SOURCE_AUTO,
            0,
            null,
            $userid
        );
        $this->enrol_in_moodle($userid, $courseid);
        $transaction->allow_commit();

        return $verdict;
    }

    /**
     * Grant access after a validated payment, and enrol.
     *
     * Same atomicity, different source. Called by the payment validation step
     * and by nothing else.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $expiresat Zero for perpetual.
     * @param int|null $grantedby Null means the current user; 0 for the
     *        auto-validator.
     * @return void
     */
    public function grant_paid_access(
        int $userid,
        int $courseid,
        int $expiresat = 0,
        ?int $grantedby = null
    ): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $this->access->grant(
            $userid,
            'course',
            $courseid,
            constants::SOURCE_PAYMENT,
            $expiresat,
            null,
            $grantedby
        );
        $this->enrol_in_moodle($userid, $courseid);
        $transaction->allow_commit();
    }

    /**
     * Whether this user is already enrolled in Moodle terms.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public function is_enrolled(int $userid, int $courseid): bool {
        return is_enrolled(\context_course::instance($courseid), $userid);
    }

    /**
     * Enrol through Moodle's manual enrolment plugin.
     *
     * Manual is deliberate. Self enrolment would put a second gatekeeper in
     * the course, with its own keys and its own opinion about who may join,
     * and the whole architecture rests on there being exactly one (§7.4).
     *
     * @param int $userid
     * @param int $courseid
     * @return void
     */
    protected function enrol_in_moodle(int $userid, int $courseid): void {
        global $DB;

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new \moodle_exception('cannotenrol', 'local_pshub');
        }

        $instance = $this->manual_instance($courseid);
        if (!$instance) {
            throw new \moodle_exception('cannotenrol', 'local_pshub');
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin->enrol_user($instance, $userid, $studentrole->id);
    }

    /**
     * The course's manual enrolment instance, created if absent.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    protected function manual_instance(int $courseid): ?\stdClass {
        global $DB;

        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
        ], '*', IGNORE_MULTIPLE);

        if ($instance) {
            return $instance;
        }

        $plugin = enrol_get_plugin('manual');
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $instanceid = $plugin->add_default_instance($course);

        return $instanceid ? $DB->get_record('enrol', ['id' => $instanceid]) : null;
    }
}
