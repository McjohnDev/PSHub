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
 * Which provider is live, and whether anybody should be worried about it.
 *
 * One place decides, so that no page has to work it out and no page can get
 * it wrong. The real provider is Flutterwave (decision 8); until
 * `flutterwave_provider` exists this returns the fake one when it is enabled
 * and refuses otherwise - which is the honest state of a platform that cannot
 * yet take money.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_factory {

    /**
     * The provider this site is using.
     *
     * @return payment_provider
     * @throws \moodle_exception When no provider is usable.
     */
    public static function get(): payment_provider {
        if (self::is_fake_enabled()) {
            return new fake_provider();
        }

        // flutterwave_provider arrives with PSH-S07-01 and a merchant account.
        // Until then, failing loudly is right: a platform that cannot take
        // money must not look as though it can.
        throw new \moodle_exception('payment_noprovider', 'local_pshub');
    }

    /**
     * Whether the fake provider is switched on.
     *
     * A LINE IN config.php, NOT A SETTING. A setting lives in the database,
     * and a database gets restored from one site onto another; a line in
     * config.php is deployed deliberately, per machine, by somebody who meant
     * it. This is the first of the four guards in docs/PAYMENTS.md.
     *
     * @return bool
     */
    public static function is_fake_enabled(): bool {
        global $CFG;

        return !empty($CFG->pshub_fake_payments);
    }

    /**
     * Whether a site is taking real money.
     *
     * Used by the admin console to decide whether to show its warning banner -
     * the fourth guard. Somebody must be able to tell, in two seconds, that
     * this site's payments are not real.
     *
     * @return bool
     */
    public static function is_live(): bool {
        return !self::is_fake_enabled();
    }
}
