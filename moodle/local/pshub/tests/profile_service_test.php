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

namespace local_pshub;

use local_pshub\profile\profile_service;

/**
 * The platform row beside a Moodle user, and the phone number on it.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\profile\profile_service
 */
final class profile_service_test extends \advanced_testcase {

    /**
     * Creating an account creates its platform row, without anyone asking.
     *
     * This is the observer's whole job (db/events.php). If it stops working,
     * the symptom appears much later and far away - a checkout that cannot
     * find a phone number - so it is tested at the point of creation.
     */
    public function test_a_new_account_gets_a_platform_row(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $row = $DB->get_record('local_pshub_user_profile', ['userid' => $user->id]);
        $this->assertNotFalse($row, 'creating a user should create their platform row');
        $this->assertEquals('learner', $row->platform_role);
        $this->assertEquals('adult', $row->audience);
    }

    /**
     * Asking twice does not produce two rows.
     *
     * ensure_for_user() is called from an observer, from the signup flow and
     * from upgrade steps, and "make sure this exists" must never be an error.
     */
    public function test_ensure_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new profile_service();

        $first = $service->ensure_for_user($user->id);
        $second = $service->ensure_for_user($user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, $DB->count_records('local_pshub_user_profile', ['userid' => $user->id]));
    }

    /**
     * A Cameroonian mobile number typed the way people type it becomes E.164.
     *
     * The aggregators want +237..., and learners write "6 77 12 34 56". If
     * this conversion drifts, payments are initiated against numbers that do
     * not exist (§3.8).
     *
     * @dataProvider phone_provider
     * @param string $typed
     * @param string|null $expected
     */
    public function test_phone_normalisation(string $typed, ?string $expected): void {
        $this->assertSame($expected, profile_service::normalise_phone($typed));
    }

    /**
     * @return array[]
     */
    public static function phone_provider(): array {
        return [
            'local, spaced as people write it' => ['6 77 12 34 56', '+237677123456'],
            'local, dashes' => ['677-12-34-56', '+237677123456'],
            'local, plain' => ['677123456', '+237677123456'],
            'already E.164' => ['+237677123456', '+237677123456'],
            'E.164 with spaces' => ['+237 677 123 456', '+237677123456'],
            'dialled the long way' => ['00237677123456', '+237677123456'],
            'another country, given explicitly' => ['+33612345678', '+33612345678'],
            'nothing usable' => ['   ', null],
            'punctuation only' => ['--', null],
        ];
    }

    /**
     * A number that is not recognisably Cameroonian is kept as typed.
     *
     * Guessing a country code here would send someone's payment prompt to
     * another network. Storing the digits is recoverable; rewriting them
     * silently is not.
     */
    public function test_an_unrecognised_number_is_not_given_a_country(): void {
        $this->assertSame('12345', profile_service::normalise_phone('12345'));
        // Nine digits, but not a Cameroonian mobile prefix.
        $this->assertSame('123456789', profile_service::normalise_phone('123456789'));
    }

    /**
     * Saving a phone stores the normalised form against the user.
     */
    public function test_set_phone_stores_normalised(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $stored = (new profile_service())->set_phone($user->id, '6 77 12 34 56');

        $this->assertSame('+237677123456', $stored);
        $this->assertSame('+237677123456',
            $DB->get_field('local_pshub_user_profile', 'phone', ['userid' => $user->id]));
    }

    /**
     * The phone typed into the signup form lands in the platform row.
     *
     * The form writes a custom profile field; the observer copies it. This
     * test does what the signup form does rather than posting the form, so it
     * stays honest about which half is being checked: the copy, not Moodle's
     * form handling.
     */
    public function test_a_signup_phone_reaches_the_platform_row(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/local/pshub/lib.php');
        $this->resetAfterTest();

        local_pshub_ensure_phone_profile_field();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $DB->get_field('user_info_field', 'id',
            ['shortname' => profile_service::PHONE_FIELD]);
        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id,
            'fieldid' => $fieldid,
            'data' => '6 77 12 34 56',
            'dataformat' => 0,
        ]);

        (new observers())::user_created(\core\event\user_created::create([
            'objectid' => $user->id,
            'relateduserid' => $user->id,
            'context' => \context_user::instance($user->id),
        ]));

        $this->assertSame('+237677123456',
            $DB->get_field('local_pshub_user_profile', 'phone', ['userid' => $user->id]));
    }
}
