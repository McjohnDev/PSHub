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
 * A payment provider that answers from a script instead of the network.
 *
 * This is what lets the whole payment flow - paywall, pending state, callback,
 * admin validation, access grant - be built and tested before a merchant
 * account exists (docs/PAYMENTS.md). It can pay, decline, go quiet, pay the
 * wrong amount, or pay in the wrong currency, which is how the paths that
 * matter get exercised at all.
 *
 * IT SAYS "PAID" FOR FREE, SO IT MUST BE IMPOSSIBLE TO SWITCH ON BY ACCIDENT.
 * The constructor refuses unless `$CFG->pshub_fake_payments` is set in
 * config.php - a line somebody deploys on purpose, not a setting in a database
 * that a production restore might carry over, and not a checkbox anybody can
 * click. It refuses rather than falling back to the real provider: a dev box
 * that quietly started charging real cards would be worse than an error.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_provider implements payment_provider {

    /** @var string The secret the fake signs its callbacks with. */
    public const SECRET = 'pshub-fake-secret';

    /** @var string Header carrying the signature, named after Flutterwave's. */
    public const SIGNATURE_HEADER = 'verif-hash';

    /** @var string Pay every time. */
    public const SCRIPT_PAY = 'pay';

    /** @var string Refuse every time. */
    public const SCRIPT_DECLINE = 'decline';

    /** @var string Never answer: the payer walked away, or the network ate it. */
    public const SCRIPT_SILENT = 'silent';

    /** @var string Pay, but not the amount that was asked for. */
    public const SCRIPT_WRONG_AMOUNT = 'wrongamount';

    /** @var string Pay the right number of the wrong currency. */
    public const SCRIPT_WRONG_CURRENCY = 'wrongcurrency';

    /** @var string What this instance does. */
    private string $script;

    /**
     * @param string|null $script One of the SCRIPT_* constants; the configured
     *        default when null.
     * @throws \moodle_exception When fake payments are not explicitly enabled.
     */
    public function __construct(?string $script = null) {
        global $CFG;

        if (empty($CFG->pshub_fake_payments)) {
            throw new \moodle_exception('fakepayments_disabled', 'local_pshub');
        }

        $this->script = $script ?? (string) (get_config('local_pshub', 'fakescript') ?: self::SCRIPT_PAY);
    }

    /**
     * @return string
     */
    public function get_name(): string {
        return 'fake';
    }

    /**
     * Begin a payment.
     *
     * Idempotent on the reference by construction: it derives everything it
     * returns from the reference, so a second call for the same one answers
     * identically and starts nothing new.
     *
     * @param payment_request $request
     * @return payment_start
     */
    public function start(payment_request $request): payment_start {
        if ($this->script === self::SCRIPT_SILENT) {
            // Started, and nothing will ever come back - the case a pending
            // screen must survive and the reconciliation task must clean up.
            return new payment_start($request->reference, $this->provider_ref($request->reference), '',
                get_string('fakepayments_waiting', 'local_pshub'));
        }

        // Card redirects, Mobile Money pushes. The difference is the whole
        // reason payment_start has two shapes (§9.2).
        if ($request->method === 'card') {
            return new payment_start(
                $request->reference,
                $this->provider_ref($request->reference),
                (new \moodle_url('/local/pshub/payment/fakecheckout.php',
                    ['reference' => $request->reference]))->out(false)
            );
        }

        return new payment_start(
            $request->reference,
            $this->provider_ref($request->reference),
            '',
            get_string('fakepayments_push', 'local_pshub', $request->msisdn)
        );
    }

    /**
     * What became of a reference.
     *
     * @param string $reference
     * @return payment_status
     */
    public function query(string $reference): payment_status {
        if ($this->script === self::SCRIPT_SILENT) {
            return new payment_status($reference, payment_status::PENDING, 0, '',
                $this->provider_ref($reference));
        }

        if ($this->script === self::SCRIPT_DECLINE) {
            return new payment_status($reference, payment_status::FAILED, 0, '',
                $this->provider_ref($reference),
                get_string('fakepayments_declined', 'local_pshub'));
        }

        // A query cannot know the amount that was asked for - only a callback
        // carries it. Zero here is honest: the caller compares against its own
        // transaction and a zero never matches.
        return new payment_status($reference, payment_status::PAID, 0, 'XAF',
            $this->provider_ref($reference));
    }

    /**
     * Turn a callback body into a status.
     *
     * Pure, as the interface requires: no database, no clock, no $CFG.
     *
     * @param string $body
     * @return payment_status
     * @throws \moodle_exception
     */
    public function parse_callback(string $body): payment_status {
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['reference'])) {
            throw new \moodle_exception('payment_callback_unreadable', 'local_pshub');
        }

        $states = [
            'successful' => payment_status::PAID,
            'failed' => payment_status::FAILED,
            'pending' => payment_status::PENDING,
        ];

        return new payment_status(
            (string) $data['reference'],
            $states[$data['status'] ?? ''] ?? payment_status::UNKNOWN,
            (int) ($data['amount'] ?? 0),
            (string) ($data['currency'] ?? ''),
            (string) ($data['provider_ref'] ?? ''),
            (string) ($data['message'] ?? '')
        );
    }

    /**
     * Whether a callback really came from this provider.
     *
     * A hash over the body with a shared secret - the same shape as
     * Flutterwave's `verif-hash`, so the call site that verifies one verifies
     * the other. hash_equals rather than ===, because a signature check that
     * leaks timing is a signature check somebody can grind down.
     *
     * @param string $body
     * @param array $headers
     * @return bool
     */
    public function verify_signature(string $body, array $headers): bool {
        $sent = '';
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, self::SIGNATURE_HEADER) === 0) {
                $sent = (string) $value;
                break;
            }
        }

        if ($sent === '') {
            return false;
        }

        return hash_equals($this->sign($body), $sent);
    }

    /**
     * The signature this provider would put on a body.
     *
     * Public so a test can produce a valid callback, which is the point of the
     * whole class.
     *
     * @param string $body
     * @return string
     */
    public function sign(string $body): string {
        return hash('sha256', $body . self::SECRET);
    }

    /**
     * A callback body this provider would send, for tests and the replay CLI.
     *
     * @param string $reference
     * @param int $amount
     * @param string $currency
     * @return string
     */
    public function build_callback(string $reference, int $amount, string $currency = 'XAF'): string {
        $status = 'successful';

        if ($this->script === self::SCRIPT_DECLINE) {
            $status = 'failed';
        } else if ($this->script === self::SCRIPT_WRONG_AMOUNT) {
            $amount = max(1, (int) floor($amount / 2));
        } else if ($this->script === self::SCRIPT_WRONG_CURRENCY) {
            $currency = $currency === 'XAF' ? 'NGN' : 'XAF';
        }

        return json_encode([
            'reference' => $reference,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'provider_ref' => $this->provider_ref($reference),
        ]);
    }

    /**
     * A stable provider reference for one of ours.
     *
     * Derived rather than random, so that starting the same payment twice
     * yields the same identifier - which is what makes start() idempotent.
     *
     * @param string $reference
     * @return string
     */
    private function provider_ref(string $reference): string {
        return 'FAKE-' . substr(hash('sha256', $reference), 0, 16);
    }
}
