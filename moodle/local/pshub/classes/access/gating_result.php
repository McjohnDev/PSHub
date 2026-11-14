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

use local_pshub\local\constants;

/**
 * The verdict from the gating service.
 *
 * Deliberately not a bare boolean. A denial carries a reason, and the reason
 * decides what the learner is shown: a paywall, a calm "we are checking your
 * payment" panel, or a renewal offer. Collapsing those into false and letting
 * each caller work out which message to show is how three different wordings
 * for the same state end up on three different screens.
 *
 * Immutable. A verdict is a fact about a moment, not a thing to edit.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gating_result {

    /** @var bool Whether the action may proceed. */
    private bool $granted;

    /** @var string Machine reason. Never shown to a learner. */
    private string $reason;

    /** @var int Id of the access right that granted this, when one did. */
    private int $accessid;

    /**
     * @param bool $granted
     * @param string $reason One of constants::GRANT or the DENY_* values.
     * @param int $accessid
     */
    private function __construct(bool $granted, string $reason, int $accessid = 0) {
        $this->granted = $granted;
        $this->reason = $reason;
        $this->accessid = $accessid;
    }

    /**
     * Open it.
     *
     * @param int $accessid The right responsible, or 0 for a free course.
     * @return self
     */
    public static function grant(int $accessid = 0): self {
        return new self(true, constants::GRANT, $accessid);
    }

    /**
     * Refuse, with a reason the client can map to copy.
     *
     * @param string $reason One of the constants::DENY_* values.
     * @return self
     */
    public static function deny(string $reason): self {
        return new self(false, $reason);
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
     * @return int
     */
    public function get_accessid(): int {
        return $this->accessid;
    }

    /**
     * The learner-facing message for this verdict.
     *
     * The identifier never reaches a learner (AC-17); this is the only place
     * that turns one into words, so the wording stays consistent everywhere.
     *
     * @return string Localised, or empty when granted.
     */
    public function get_message(): string {
        if ($this->granted) {
            return '';
        }

        return get_string('denied_' . $this->reason, 'local_pshub');
    }
}
