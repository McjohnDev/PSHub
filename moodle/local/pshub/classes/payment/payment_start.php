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

namespace local_pshub\payment;

/**
 * What the client must do next, once a payment has been started.
 *
 * Two shapes, because the two families of method behave differently (§9.2):
 * a card sends the browser somewhere, Mobile Money sends a prompt to a handset
 * and leaves the browser waiting. The page that renders this must never assume
 * the first: a redirect that never comes is the commonest way a MoMo checkout
 * is got wrong.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_start {

    /**
     * @param string $reference Echoed back, so the caller can be sure which payment this is.
     * @param string $providerref The provider's own identifier, when it gives one at once.
     * @param string $redirecturl Where to send the browser; empty for a push flow.
     * @param string $instructions What to tell the payer while they wait; empty for a redirect.
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $providerref = '',
        public readonly string $redirecturl = '',
        public readonly string $instructions = ''
    ) {
    }

    /**
     * Whether the browser should be sent somewhere.
     *
     * @return bool
     */
    public function is_redirect(): bool {
        return $this->redirecturl !== '';
    }
}
