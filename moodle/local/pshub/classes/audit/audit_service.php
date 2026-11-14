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

namespace local_pshub\audit;

use stdClass;

/**
 * The audit trail for decisions that move money or open content.
 *
 * Two questions must be answerable months later: who published this course,
 * and who let this payment through (cahier §7). Everything else that happens
 * on the site is Moodle's own logstore problem.
 *
 * Append-only by construction. There is no update method and no delete method,
 * and none should be added: an audit trail an administrator can edit is not an
 * audit trail.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_service {

    /** @var string A teacher submitted a course for validation. */
    /** @var string A live session was put in the diary. */
    public const ACTION_SESSION_SCHEDULED = 'session_scheduled';
    /** @var string A live session was moved or its length changed. */
    public const ACTION_SESSION_RESCHEDULED = 'session_rescheduled';
    /** @var string A live session was closed at its cap by the system. */
    public const ACTION_SESSION_ENDED = 'session_ended';
    /** @var string A live session was called off. */
    public const ACTION_SESSION_CANCELLED = 'session_cancelled';
    /** @var string A hub course was created, as a draft. */
    public const ACTION_COURSE_CREATED = 'course_created';
    /** @var string A hub course's catalogue metadata was changed. */
    public const ACTION_COURSE_UPDATED = 'course_updated';
    public const ACTION_COURSE_SUBMITTED = 'course_submitted';
    /** @var string An admin approved a course. */
    public const ACTION_COURSE_PUBLISHED = 'course_published';
    /** @var string An admin rejected a course. */
    public const ACTION_COURSE_REJECTED = 'course_rejected';
    /** @var string A course left the catalogue. */
    public const ACTION_COURSE_ARCHIVED = 'course_archived';
    /** @var string A payment reached paid_unvalidated. */
    public const ACTION_PAYMENT_RECEIVED = 'payment_received';
    /** @var string A payment was validated and access opened. */
    public const ACTION_PAYMENT_VALIDATED = 'payment_validated';
    /** @var string A payment was refused. */
    public const ACTION_PAYMENT_REJECTED = 'payment_rejected';
    /** @var string An access right was granted by hand. */
    public const ACTION_ACCESS_GRANTED = 'access_granted';
    /** @var string An access right was withdrawn. */
    public const ACTION_ACCESS_REVOKED = 'access_revoked';

    /** @var string Audited subject: a course. */
    public const TARGET_COURSE = 'course';
    /** @var string Audited subject: a live session. */
    public const TARGET_SESSION = 'session';
    /** @var string Audited subject: a transaction. */
    public const TARGET_TRANSACTION = 'transaction';
    /** @var string Audited subject: an access right. */
    public const TARGET_ACCESS = 'access';

    /**
     * Record a transition.
     *
     * Call inside the same transaction as the change it describes, so a
     * committed change always has its audit row and a rolled-back one leaves
     * nothing behind.
     *
     * @param string $action One of the ACTION_* constants.
     * @param string $targettype One of the TARGET_* constants.
     * @param int $targetid Id of the thing that changed.
     * @param array|stdClass|null $before State before, or null when creating.
     * @param array|stdClass|null $after State after, or null when deleting.
     * @param int|null $actorid Who is responsible. Null means the current
     *        user; pass 0 explicitly for system actions such as
     *        auto-validation, so the trail never claims a person approved
     *        something the system did.
     * @return int Id of the audit row.
     */
    public function record(
        string $action,
        string $targettype,
        int $targetid,
        $before = null,
        $after = null,
        ?int $actorid = null
    ): int {
        global $DB, $USER;

        $row = new stdClass();
        $row->actorid = $actorid ?? (int) $USER->id;
        $row->action = $action;
        $row->target_type = $targettype;
        $row->target_id = $targetid;
        $row->before_json = $this->encode($before);
        $row->after_json = $this->encode($after);
        $row->ip = $this->client_ip();
        $row->timecreated = time();

        return (int) $DB->insert_record('local_pshub_audit', $row);
    }

    /**
     * The trail for one thing, newest first.
     *
     * @param string $targettype One of the TARGET_* constants.
     * @param int $targetid
     * @param int $limit
     * @return array Audit records.
     */
    public function history(string $targettype, int $targetid, int $limit = 50): array {
        global $DB;

        return $DB->get_records(
            'local_pshub_audit',
            ['target_type' => $targettype, 'target_id' => $targetid],
            'timecreated DESC, id DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Serialise a state snapshot.
     *
     * Password-, secret- and token-shaped keys are dropped rather than stored.
     * This trail is read by support staff during an incident, which is exactly
     * when a credential sitting in a log column does the most damage.
     *
     * @param array|stdClass|null $state
     * @return string|null JSON, or null when there is nothing to record.
     */
    protected function encode($state): ?string {
        if ($state === null) {
            return null;
        }

        $data = (array) $state;
        foreach (array_keys($data) as $key) {
            $lower = \core_text::strtolower((string) $key);
            if (str_contains($lower, 'password')
                    || str_contains($lower, 'secret')
                    || str_contains($lower, 'token')) {
                unset($data[$key]);
            }
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Caller IP, truncated to fit an IPv6 address.
     *
     * @return string|null
     */
    protected function client_ip(): ?string {
        $ip = getremoteaddr(null);

        return $ip === null ? null : \core_text::substr($ip, 0, 45);
    }
}
