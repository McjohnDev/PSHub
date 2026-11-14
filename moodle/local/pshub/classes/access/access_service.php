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

use local_pshub\audit\audit_service;
use local_pshub\local\constants;
use stdClass;

/**
 * The lifecycle of an access right.
 *
 * Every route that opens content ends here. Nothing else writes
 * local_pshub_access: not the payment gateway, not a support script, not an
 * admin form. One writer means one place to audit and one place to be wrong.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_service {

    /** @var audit_service */
    private audit_service $audit;

    /**
     * @param audit_service|null $audit
     */
    public function __construct(?audit_service $audit = null) {
        $this->audit = $audit ?? new audit_service();
    }

    /**
     * The right a user holds over a target, whatever its state.
     *
     * @param int $userid
     * @param string $targettype course|pack|programme
     * @param int $targetid
     * @return stdClass|false
     */
    public function find(int $userid, string $targettype, int $targetid) {
        global $DB;

        return $DB->get_record('local_pshub_access', [
            'userid' => $userid,
            'target_type' => $targettype,
            'target_id' => $targetid,
        ]);
    }

    /**
     * Whether a right is usable right now.
     *
     * Expiry is evaluated here rather than trusted from the status column,
     * because a right expires by the passage of time and not by a cron run
     * having noticed. A learner whose access lapsed four minutes ago is
     * refused even though the sweeper has not run yet.
     *
     * @param stdClass|false $right
     * @param int|null $now
     * @return bool
     */
    public function is_usable($right, ?int $now = null): bool {
        if (!$right) {
            return false;
        }
        if ($right->status !== constants::ACCESS_ACTIVE) {
            return false;
        }
        $expiry = (int) $right->expires_at;
        if ($expiry === 0) {
            return true;
        }

        return $expiry > ($now ?? time());
    }

    /**
     * Grant, or reactivate, a right.
     *
     * The unique key on (userid, target_type, target_id) means a user has at
     * most one row per target, so this is an upsert rather than an insert. A
     * previously revoked or expired right is reactivated in place, which keeps
     * the grant history on one row instead of scattering it.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param string $source auto|payment|manual
     * @param int $expiresat Zero for perpetual.
     * @param string|null $reason Required for manual grants.
     * @param int|null $grantedby Null means the current user.
     * @return stdClass The stored right.
     */
    public function grant(
        int $userid,
        string $targettype,
        int $targetid,
        string $source,
        int $expiresat = 0,
        ?string $reason = null,
        ?int $grantedby = null
    ): stdClass {
        global $DB, $USER;

        if ($source === constants::SOURCE_MANUAL && ($reason === null || trim($reason) === '')) {
            throw new \coding_exception('A manual grant requires a reason.');
        }

        $now = time();
        $existing = $this->find($userid, $targettype, $targetid);

        $record = new stdClass();
        $record->userid = $userid;
        $record->target_type = $targettype;
        $record->target_id = $targetid;
        $record->source = $source;
        $record->status = constants::ACCESS_ACTIVE;
        $record->expires_at = $expiresat;
        $record->grantedby = $grantedby ?? (int) $USER->id;
        $record->grantreason = $reason;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_pshub_access', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('local_pshub_access', $record);
        }

        // Automatic grants on free courses are the common path and would drown
        // the trail. Audit the decisions a person made or money caused.
        if ($source !== constants::SOURCE_AUTO) {
            $this->audit->record(
                audit_service::ACTION_ACCESS_GRANTED,
                audit_service::TARGET_ACCESS,
                (int) $record->id,
                $existing ?: null,
                $record,
                $grantedby
            );
        }

        return $record;
    }

    /**
     * Withdraw a right.
     *
     * Revoking does not delete. A learner who paid and was later refunded
     * leaves a record of both facts.
     *
     * @param int $accessid
     * @param string $reason
     * @return void
     */
    public function revoke(int $accessid, string $reason): void {
        global $DB;

        $before = $DB->get_record('local_pshub_access', ['id' => $accessid], '*', MUST_EXIST);

        $after = clone $before;
        $after->status = constants::ACCESS_REVOKED;
        $after->grantreason = $reason;
        $after->timemodified = time();
        $DB->update_record('local_pshub_access', $after);

        $this->audit->record(
            audit_service::ACTION_ACCESS_REVOKED,
            audit_service::TARGET_ACCESS,
            $accessid,
            $before,
            $after
        );
    }

    /**
     * Mark lapsed rights expired.
     *
     * Housekeeping for reporting, not for correctness: is_usable() already
     * refuses a lapsed right the moment it lapses, so this task failing for a
     * week lets nobody in who should not be.
     *
     * @param int|null $now
     * @return int Number of rights expired.
     */
    public function expire_due(?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $select = 'status = :active AND expires_at > 0 AND expires_at <= :now';
        $params = ['active' => constants::ACCESS_ACTIVE, 'now' => $now];

        $count = $DB->count_records_select('local_pshub_access', $select, $params);
        if ($count > 0) {
            $DB->set_field_select(
                'local_pshub_access',
                'status',
                constants::ACCESS_EXPIRED,
                $select,
                $params
            );
        }

        return $count;
    }

    /**
     * Active packs whose membership includes a given course.
     *
     * @param int $courseid
     * @return int[]
     */
    public function packs_containing_course(int $courseid): array {
        global $DB;

        $sql = "SELECT p.id
                  FROM {local_pshub_pack} p
                  JOIN {local_pshub_pack_course} pc ON pc.packid = p.id
                 WHERE pc.courseid = :courseid AND p.is_active = 1";

        return array_map('intval', array_keys($DB->get_records_sql($sql, ['courseid' => $courseid])));
    }
}
