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
 * What the platform needs from whoever takes the money.
 *
 * The seam that lets the whole payment flow be built and tested before a
 * merchant account exists (docs/PAYMENTS.md). Flutterwave is one
 * implementation; fake_provider is the other, and it answers from a script
 * with no network at all.
 *
 * THREE RULES THIS INTERFACE EXISTS TO HOLD
 *
 * 1. **parse_callback() and verify_signature() touch nothing.** No database,
 *    no clock, no $CFG. That is what makes them testable against a recorded
 *    payload in a unit test rather than an integration test.
 * 2. **The provider never grants anything.** It reports. An access right is
 *    created by the validation path (§3.8) from a validated transaction and by
 *    nothing else - a provider that could open a course would make the fake
 *    one a security hole rather than a test tool.
 * 3. **The provider never writes the transaction.** The caller does, so there
 *    is one place where `pending → paid_unvalidated` happens and one place
 *    where idempotency is enforced (AC-12).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface payment_provider {

    /**
     * The provider's own name, as it is written on a transaction row.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Begin a payment.
     *
     * Returns what the client needs in order to continue: a redirect URL for
     * a card, or the instructions for a Mobile Money push.
     *
     * MUST BE IDEMPOTENT ON THE REFERENCE. Calling it twice for the same
     * reference starts one payment, not two - a learner who taps "pay" and
     * then reloads must not be charged twice.
     *
     * @param payment_request $request
     * @return payment_start
     */
    public function start(payment_request $request): payment_start;

    /**
     * Ask the provider what became of a reference.
     *
     * Used by the pending screen's poll and by the reconciliation task (§9.3).
     * Never trusted from the client: the client says which reference, the
     * provider says what happened to it.
     *
     * @param string $reference
     * @return payment_status
     */
    public function query(string $reference): payment_status;

    /**
     * Turn a raw callback body into a status, or throw.
     *
     * Pure: no database, no HTTP, no clock.
     *
     * @param string $body
     * @return payment_status
     * @throws \moodle_exception When the body is not something this provider sent.
     */
    public function parse_callback(string $body): payment_status;

    /**
     * Whether this callback really came from the provider.
     *
     * Pure, and the only thing standing between the callback endpoint and
     * anybody on the internet who can read our URL.
     *
     * @param string $body
     * @param array $headers
     * @return bool
     */
    public function verify_signature(string $body, array $headers): bool;
}
