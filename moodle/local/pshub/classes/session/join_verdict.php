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

namespace local_pshub\session;

use stdClass;

/**
 * The answer to "may I walk into this session now", and why not.
 *
 * A boolean would be enough for the door. It is not enough for the person
 * standing at it: "too early" and "full" and "your access has lapsed" need
 * three different sentences and three different next steps, and a page that
 * only knows `false` says "you cannot join" to all of them (§15).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class join_verdict {

    /** @var string The door is open. */
    public const REASON_OK = 'ok';
    /** @var string No such session. */
    public const REASON_UNKNOWN = 'unknown';
    /** @var string The class was called off. */
    public const REASON_CANCELLED = 'cancelled';
    /** @var string It is over. */
    public const REASON_ENDED = 'ended';
    /** @var string The door has not opened yet. */
    public const REASON_TOO_EARLY = 'tooearly';
    /** @var string The group is at its size. */
    public const REASON_FULL = 'full';
    /** @var string Enrolment or payment no longer covers this. */
    public const REASON_NO_ACCESS = 'noaccess';

    /** @var bool */
    private bool $granted;

    /** @var string One of the REASON_* constants. */
    private string $reason;

    /** @var stdClass|null The session, when there is one. */
    private ?stdClass $session;

    /**
     * @param bool $granted
     * @param string $reason
     * @param stdClass|null $session
     */
    public function __construct(bool $granted, string $reason, ?stdClass $session = null) {
        $this->granted = $granted;
        $this->reason = $reason;
        $this->session = $session;
    }

    /**
     * @return bool
     */
    public function is_granted(): bool {
        return $this->granted;
    }

    /**
     * @return string
     */
    public function get_reason(): string {
        return $this->reason;
    }

    /**
     * @return stdClass|null
     */
    public function get_session(): ?stdClass {
        return $this->session;
    }

    /**
     * What to tell the person, in their own language.
     *
     * @return string
     */
    public function get_message(): string {
        return get_string('join_' . $this->reason, 'local_pshub');
    }

    /**
     * When the door opens, for a verdict that is only early.
     *
     * The page shows a countdown from this and the server's own clock - never
     * the device's.
     *
     * @return int|null Unix timestamp, or null when the question does not apply.
     */
    public function opens_at(): ?int {
        if ($this->reason !== self::REASON_TOO_EARLY || !$this->session) {
            return null;
        }

        return (int) $this->session->scheduled_start
            - (session_service::JOIN_WINDOW_MINUTES * MINSECS);
    }
}
