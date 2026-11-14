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
 * What a provider says became of a payment.
 *
 * THE AMOUNT AND CURRENCY ARE PART OF THE ANSWER, not decoration. A callback
 * that says "paid" without saying how much is a callback that cannot be
 * checked, and §9.3 requires the amount AND the currency to be verified
 * against the transaction before anything is granted. A provider that paid
 * 1 000 XAF for a 25 000 XAF course has not paid for the course.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_status {

    /** @var string The money is in. */
    public const PAID = 'paid';

    /** @var string Still waiting: the payer has not finished, or the provider has not said. */
    public const PENDING = 'pending';

    /** @var string Refused, cancelled, or timed out at the provider. */
    public const FAILED = 'failed';

    /** @var string The provider does not know this reference. */
    public const UNKNOWN = 'unknown';

    /**
     * @param string $reference Ours.
     * @param string $state One of the constants above.
     * @param int $amount What was actually paid, in the smallest unit.
     * @param string $currency ISO 4217, as the provider reports it.
     * @param string $providerref The provider's own identifier for this payment.
     * @param string $message Anything worth showing a human, never a code.
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $state,
        public readonly int $amount = 0,
        public readonly string $currency = '',
        public readonly string $providerref = '',
        public readonly string $message = ''
    ) {
    }

    /**
     * @return bool
     */
    public function is_paid(): bool {
        return $this->state === self::PAID;
    }

    /**
     * Whether this answer matches what was asked for.
     *
     * Both halves, every time. A payment in the right amount but the wrong
     * currency is the oldest trick there is: 25 000 of something is not
     * 25 000 XAF.
     *
     * @param int $expectedamount
     * @param string $expectedcurrency
     * @return bool
     */
    public function matches(int $expectedamount, string $expectedcurrency): bool {
        return $this->amount === $expectedamount
            && strcasecmp($this->currency, $expectedcurrency) === 0;
    }
}
