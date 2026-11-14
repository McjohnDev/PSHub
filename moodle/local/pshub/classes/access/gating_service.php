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

namespace local_pshub\access;

use local_pshub\catalogue\price_resolver;
use local_pshub\local\constants;

/**
 * The gatekeeper.
 *
 * Called before every sensitive action: opening a course, joining a session,
 * starting a quiz, downloading a resource. Called by renderers, portal pages,
 * web-service functions and the mobile app's server side. Never by a client,
 * and never trusted from one.
 *
 * The golden rule this class exists to hold: access authorisation lives here,
 * never in Moodle's per-course enrolment configuration. Courses carry no
 * enrolment keys, no cohort rules, no enrol_fee conditions. Pedagogy does not
 * decide who may pay to see it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gating_service {

    /** @var access_service */
    private access_service $access;

    /** @var price_resolver */
    private price_resolver $prices;

    /**
     * @param access_service|null $access
     * @param price_resolver|null $prices
     */
    public function __construct(?access_service $access = null, ?price_resolver $prices = null) {
        $this->access = $access ?? new access_service();
        $this->prices = $prices ?? new price_resolver();
    }

    /**
     * Whether packs may grant access.
     *
     * The pack branch in check() is built, tested and shipped in P1, and this
     * flag holds it shut until P2. That is what makes AC-18 assertable rather
     * than aspirational: a learner holding a valid pack right in a P1 build is
     * still refused, and the proof is a test rather than the absence of a
     * feature.
     *
     * @return bool
     */
    public function are_packs_enabled(): bool {
        return (bool) get_config('local_pshub', 'packsenabled');
    }

    /**
     * May this user open this course?
     *
     * @param int $userid
     * @param int $courseid Moodle course id.
     * @param int|null $now Injectable clock, for tests.
     * @return gating_result
     */
    public function check(int $userid, int $courseid, ?int $now = null): gating_result {
        global $DB;

        $now = $now ?? time();
        $hubcourse = $DB->get_record('local_pshub_course', ['courseid' => $courseid]);

        // A course this platform does not manage is not ours to open. Denying
        // rather than throwing keeps a stale link from putting a stack trace
        // in front of a learner.
        if (!$hubcourse) {
            return gating_result::deny(constants::DENY_NOT_AVAILABLE);
        }

        // An unpublished course exists for its teacher and for admins, so both
        // can see the thing under review. To everyone else it is not there at
        // all (AC-1).
        if ($hubcourse->review_status !== constants::REVIEW_PUBLISHED
                && !$this->may_preview($userid, $courseid)) {
            return gating_result::deny(constants::DENY_NOT_AVAILABLE);
        }

        // Free is free, whether by access_type or by an administrator having
        // attached a free-granting course type (spec §3.3).
        if ($this->prices->is_effectively_free($courseid)) {
            return gating_result::grant();
        }

        // Course-level right.
        $right = $this->access->find($userid, 'course', $courseid);
        if ($this->access->is_usable($right, $now)) {
            return gating_result::grant((int) $right->id);
        }

        // Pack-level right. Held shut for all of P1.
        if ($this->are_packs_enabled()) {
            foreach ($this->access->packs_containing_course($courseid) as $packid) {
                $packright = $this->access->find($userid, 'pack', $packid);
                if ($this->access->is_usable($packright, $now)) {
                    return gating_result::grant((int) $packright->id);
                }
            }
        }

        // A right that existed and lapsed is a different conversation from one
        // that never existed. The first gets "renew", the second gets a
        // paywall, and telling them apart is the difference between a
        // returning customer and a cold sell. A revoked right is neither: it
        // was taken away, so the paywall is correct.
        if ($right && $right->status !== constants::ACCESS_REVOKED) {
            return gating_result::deny(constants::DENY_ACCESS_EXPIRED);
        }

        // Paid, waiting on a human. Not an error, and not styled as one.
        if ($this->has_unvalidated_payment($userid, $courseid)) {
            return gating_result::deny(constants::DENY_PAYMENT_PENDING);
        }

        return gating_result::deny(constants::DENY_PAYMENT_REQUIRED);
    }

    /**
     * Convenience wrapper that throws instead of returning a verdict.
     *
     * For call sites where a denial is exceptional rather than expected, such
     * as a web-service function reached with a crafted course id.
     *
     * @param int $userid
     * @param int $courseid
     * @return void
     */
    public function require_access(int $userid, int $courseid): void {
        $result = $this->check($userid, $courseid);
        if (!$result->is_granted()) {
            throw new \moodle_exception('denied_' . $result->get_reason(), 'local_pshub');
        }
    }

    /**
     * Whether a user may see a course that is not published.
     *
     * Its own teacher, and anyone who can approve courses. Note this reads
     * Moodle capabilities rather than the platform_role column: the column is
     * for display, capabilities are for decisions.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    protected function may_preview(int $userid, int $courseid): bool {
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if ($coursecontext && has_capability('local/pshub:submitcourse', $coursecontext, $userid)) {
            return true;
        }

        return has_capability(
            'local/pshub:publishcourse',
            \context_system::instance(),
            $userid
        );
    }

    /**
     * Whether money has arrived for this course but an admin has not yet
     * released it (spec §3.8).
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    protected function has_unvalidated_payment(int $userid, int $courseid): bool {
        global $DB;

        return $DB->record_exists('local_pshub_transaction', [
            'userid' => $userid,
            'target_type' => 'course',
            'target_id' => $courseid,
            'status' => constants::TXN_PAID_UNVALIDATED,
        ]);
    }
}
