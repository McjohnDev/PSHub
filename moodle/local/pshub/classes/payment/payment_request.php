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
 * What the platform asks a provider to collect.
 *
 * Readonly: a request that could be edited after it was built is a request
 * whose amount might not be the amount that was checked.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_request {

    /**
     * @param string $reference Ours, unique, and what every later answer is keyed on.
     * @param int $amount In the currency's smallest unit. XAF has no subunit,
     *        so for Cameroon this is simply francs - but the field is named
     *        for the general case because the day a decimal currency is added
     *        is not the day to discover the assumption.
     * @param string $currency ISO 4217.
     * @param string $method One of constants::METHODS.
     * @param string $msisdn The payer's number, for Mobile Money; empty for card.
     * @param string $description What the learner will see on their statement.
     * @param string $returnurl Where the provider sends the browser afterwards.
     */
    public function __construct(
        public readonly string $reference,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $method,
        public readonly string $msisdn = '',
        public readonly string $description = '',
        public readonly string $returnurl = ''
    ) {
    }
}
