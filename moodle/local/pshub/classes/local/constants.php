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

namespace local_pshub\local;

/**
 * The string values persisted in local_pshub tables.
 *
 * These are stored values, not labels. Changing one is a database migration,
 * so they live in one place and are never typed as literals elsewhere.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class constants {

    /** @var string Course is being written. Visible only to its teacher. */
    public const REVIEW_DRAFT = 'draft';
    /** @var string Submitted, waiting for an admin. */
    public const REVIEW_PENDING = 'pending';
    /** @var string Approved. Equivalent to course.visible = 1. */
    public const REVIEW_PUBLISHED = 'published';
    /** @var string Rejected with a required comment. */
    public const REVIEW_REJECTED = 'rejected';
    /** @var string Withdrawn from the catalogue without revoking existing access. */
    public const REVIEW_ARCHIVED = 'archived';

    /** @var string No payment required. */
    public const ACCESS_TYPE_FREE = 'free';
    /** @var string Requires a valid access right. */
    public const ACCESS_TYPE_PAID = 'paid';

    /** @var string Granted automatically on a free course. */
    public const SOURCE_AUTO = 'auto';
    /** @var string Granted by a validated payment. */
    public const SOURCE_PAYMENT = 'payment';
    /** @var string Granted by a person, with a reason, audited. */
    public const SOURCE_MANUAL = 'manual';

    /** @var string Right is usable now. */
    public const ACCESS_ACTIVE = 'active';
    /** @var string Awaiting something. Does not open content. */
    public const ACCESS_PENDING = 'pending';
    /** @var string Past its expiry. */
    public const ACCESS_EXPIRED = 'expired';
    /** @var string Withdrawn. */
    public const ACCESS_REVOKED = 'revoked';

    /** @var string Checkout started, nothing confirmed. */
    public const TXN_PENDING = 'pending';
    /** @var string Provider confirmed the money. Access is still closed. */
    public const TXN_PAID_UNVALIDATED = 'paid_unvalidated';
    /** @var string Validated. The only status that opens access. */
    public const TXN_VALIDATED = 'validated';
    /** @var string Refused by an admin. */
    public const TXN_REJECTED = 'rejected';
    /** @var string Provider declined. */
    public const TXN_FAILED = 'failed';
    /** @var string Money returned. */
    public const TXN_REFUNDED = 'refunded';

    /** @var string[] Payment methods, in display order. */
    public const METHODS = [
        'mtn_momo',
        'orange_money',
        'wave',
        'card',
        'bank_transfer',
        'cash',
    ];

    /** @var string[] Methods an admin records by hand in the back office. */
    public const OFFLINE_METHODS = ['bank_transfer', 'cash'];

    /** @var string Scheduled, not yet started. */
    public const SESSION_SCHEDULED = 'scheduled';
    /** @var string Running. */
    public const SESSION_LIVE = 'live';
    /** @var string Finished, or force-ended by the cap. */
    public const SESSION_ENDED = 'ended';
    /** @var string Called off. */
    public const SESSION_CANCELLED = 'cancelled';

    /** @var int Join opens this many seconds before the scheduled start. */
    public const JOIN_WINDOW_SECONDS = 900;

    /** @var int Default cap on a live session, in minutes (cahier §5.3). */
    public const DEFAULT_SESSION_MINUTES = 50;

    /** @var int[] Reminder offsets in minutes before start (cahier §5.6). */
    public const REMINDER_OFFSETS = [1440, 60, 10];

    /** @var string Reminder sent to the teacher. */
    public const AUDIENCE_TEACHER = 'teacher';
    /** @var string Reminder sent to enrolled learners. */
    public const AUDIENCE_LEARNERS = 'learners';

    /**
     * @var float Share of the scheduled duration a learner must attend before
     * it counts. Ninety seconds in a fifty minute class is not attendance.
     */
    public const PRESENCE_THRESHOLD = 0.6;

    /** @var string XP for attending a live session. */
    public const XP_LIVE_ATTENDANCE = 'live_attendance';
    /** @var string XP for passing a quiz. */
    public const XP_QUIZ_PASSED = 'quiz_passed';
    /** @var string XP for submitting an assignment. */
    public const XP_ASSIGNMENT_SUBMITTED = 'assignment_submitted';
    /** @var string XP for completing a workshop. */
    public const XP_WORKSHOP_COMPLETED = 'workshop_completed';
    /** @var string XP for completing a course. */
    public const XP_COURSE_COMPLETED = 'course_completed';

    /** @var string Gating verdict: open it. */
    public const GRANT = 'grant';
    /** @var string Denial: needs paying for. */
    public const DENY_PAYMENT_REQUIRED = 'payment_required';
    /** @var string Denial: paid, waiting on an admin. */
    public const DENY_PAYMENT_PENDING = 'payment_pending';
    /** @var string Denial: not published, or not for this viewer. */
    public const DENY_NOT_AVAILABLE = 'not_available';
    /** @var string Denial: the right lapsed. */
    public const DENY_ACCESS_EXPIRED = 'access_expired';

    /**
     * A namespace for values, not a thing to instantiate.
     */
    private function __construct() {
    }
}
