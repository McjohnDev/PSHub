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

namespace local_pshub\profile;

use stdClass;

/**
 * The platform row that sits beside a Moodle user.
 *
 * There is one user record, in Moodle's `user` table, and this never
 * duplicates it: no name, no e-mail, no password. What lives here is what the
 * platform needs and Moodle has no column for - the Mobile Money number, the
 * display role, the audience filter (§4.1).
 *
 * Every learner has exactly one of these rows, created the moment the account
 * is (see db/events.php). Nothing else in the plugin may assume the row exists
 * without calling ensure_for_user() first: users arrive by signup, by CLI, by
 * upload and by web service, and only this class has to know that.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_service {

    /** @var string The custom user profile field that captures the phone at signup. */
    public const PHONE_FIELD = 'pshubphone';

    /** @var string Cameroon's country calling code. The platform launches here (§1). */
    private const CAMEROON_CC = '+237';

    /**
     * The platform row for a user, created if it is not there yet.
     *
     * Idempotent on purpose. It is called from an event observer, from the
     * signup flow and from tests, and calling it twice must not produce two
     * rows - the table's unique key on userid would refuse the second one
     * anyway, and an exception is not an answer to "make sure this exists".
     *
     * @param int $userid
     * @return stdClass The row.
     */
    public function ensure_for_user(int $userid): stdClass {
        global $DB;

        $existing = $DB->get_record('local_pshub_user_profile', ['userid' => $userid]);
        if ($existing) {
            return $existing;
        }

        $now = time();
        $record = (object) [
            'userid' => $userid,
            // Display only. Authorisation is by capability and never by this
            // column (§5.3) - a row saying "admin" grants nothing.
            'platform_role' => 'learner',
            'phone' => null,
            'preferred_lang' => $this->language_for($userid),
            'audience' => 'adult',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $record->id = $DB->insert_record('local_pshub_user_profile', $record);

        return $record;
    }

    /**
     * Record a learner's Mobile Money number.
     *
     * Stored in E.164 because that is what the payment aggregators take, and
     * converting at the point of payment would mean every caller guessing the
     * same rules slightly differently (§3.8).
     *
     * @param int $userid
     * @param string $phone As the learner typed it.
     * @return string|null What was stored.
     */
    public function set_phone(int $userid, string $phone): ?string {
        global $DB;

        $this->ensure_for_user($userid);

        $normalised = self::normalise_phone($phone);

        $DB->set_field('local_pshub_user_profile', 'phone', $normalised, ['userid' => $userid]);
        $DB->set_field('local_pshub_user_profile', 'timemodified', time(), ['userid' => $userid]);

        return $normalised;
    }

    /**
     * A typed phone number in E.164, where that can be done without guessing.
     *
     * The rules, and the reason each one stops where it does:
     *
     *  - Punctuation goes. "6 77 12 34 56" and "677-12-34-56" are the same
     *    number and a learner should not have to know which we wanted.
     *  - A leading "+" or "00" means the learner already gave a country, so
     *    the country is theirs, not ours.
     *  - NINE digits starting with 6 is a Cameroonian mobile number, which is
     *    how this platform's learners write their own number. Those get +237.
     *  - Anything else is stored as the learner typed it, minus punctuation.
     *    A wrong guess here sends someone's payment prompt to another
     *    country's network; storing what they typed is recoverable, silently
     *    rewriting it is not.
     *
     * @param string $phone
     * @return string|null Null when there is nothing usable in it.
     */
    public static function normalise_phone(string $phone): ?string {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        // Keep a leading + and the digits; drop spaces, dots, dashes, brackets.
        $plus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed);

        if ($digits === '') {
            return null;
        }

        if ($plus) {
            return '+' . $digits;
        }

        // International prefix dialled the long way.
        if (str_starts_with($digits, '00')) {
            return '+' . substr($digits, 2);
        }

        // A local Cameroonian mobile number: nine digits, first one a 6.
        if (strlen($digits) === 9 && str_starts_with($digits, '6')) {
            return self::CAMEROON_CC . $digits;
        }

        return $digits;
    }

    /**
     * The language to start a learner on.
     *
     * Their Moodle language when they have one, otherwise the site's - which
     * is French (§12.4). Never hard-coded here, so a site that changes its
     * default does not leave new learners on the old one.
     *
     * @param int $userid
     * @return string
     */
    private function language_for(int $userid): string {
        global $DB, $CFG;

        $lang = $DB->get_field('user', 'lang', ['id' => $userid]);
        if (empty($lang)) {
            $lang = $CFG->lang ?? 'fr';
        }

        // The column holds five characters: "fr", "en", "fr_ca".
        return substr($lang, 0, 5);
    }
}
