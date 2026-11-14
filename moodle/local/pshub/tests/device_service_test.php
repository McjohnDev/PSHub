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

use local_pshub\external\deregister_device;
use local_pshub\external\register_device;
use local_pshub\push\device_service;

/**
 * Which handsets a person can be reached on - and whose they are.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\push\device_service
 * @covers     \local_pshub\external\register_device
 * @covers     \local_pshub\external\deregister_device
 */
final class device_service_test extends \advanced_testcase {

    /**
     * A handset is remembered.
     */
    public function test_a_device_is_remembered(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $id = (new device_service())->register($user->id, 'tok-abc', 'android');

        $row = $DB->get_record('local_pshub_push_token', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame((int) $user->id, (int) $row->userid);
        $this->assertSame('tok-abc', $row->token);
        $this->assertSame('android', $row->platform);
    }

    /**
     * Registering the same token twice is one device.
     *
     * The app registers on every launch, because the operating system
     * refreshes a push token whenever it chooses. Two rows would mean two
     * reminders on one lock screen.
     */
    public function test_registering_twice_is_one_device(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new device_service();

        $first = $service->register($user->id, 'tok-abc', 'android');
        $second = $service->register($user->id, 'tok-abc', 'android');

        $this->assertSame($first, $second);
        $this->assertSame(1, $DB->count_records('local_pshub_push_token'));
    }

    /**
     * A handset that changes hands changes owner.
     *
     * THE REASON THE TOKEN COLUMN IS UNIQUE. A phone gets passed on, sold, or
     * shared; the push token stays the same and the person behind it does
     * not. If the old row survived, the first owner would keep receiving
     * class reminders on somebody else's lock screen.
     */
    public function test_a_device_that_changes_hands_changes_owner(): void {
        global $DB;
        $this->resetAfterTest();

        $mother = $this->getDataGenerator()->create_user();
        $daughter = $this->getDataGenerator()->create_user();
        $service = new device_service();

        $service->register($mother->id, 'tok-shared', 'android');
        $service->register($daughter->id, 'tok-shared', 'android');

        $this->assertSame(1, $DB->count_records('local_pshub_push_token'));
        $this->assertSame((int) $daughter->id,
            (int) $DB->get_field('local_pshub_push_token', 'userid', ['token' => 'tok-shared']));
        $this->assertSame([], $service->get_devices($mother->id),
            'the previous owner is no longer reachable there');
    }

    /**
     * One person, several handsets.
     */
    public function test_one_person_may_have_several_devices(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new device_service();

        $service->register($user->id, 'tok-phone', 'android');
        $service->register($user->id, 'tok-tablet', 'ios');

        $this->assertCount(2, $service->get_devices($user->id));
    }

    /**
     * The platform has to be one we build for.
     */
    public function test_an_unknown_platform_is_refused(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new device_service())->register($user->id, 'tok-abc', 'symbian');
    }

    /**
     * An empty token is a mistake, not a device.
     */
    public function test_an_empty_token_is_refused(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new device_service())->register($user->id, '   ', 'android');
    }

    /**
     * And one longer than the column is refused rather than silently cut.
     *
     * A truncated token is a token that never matches again: the device is
     * registered, nothing is ever delivered, and nothing anywhere says why.
     */
    public function test_an_over_long_token_is_refused(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new device_service())->register($user->id,
            str_repeat('x', device_service::TOKEN_MAX + 1), 'android');
    }

    /**
     * Signing out forgets the handset.
     */
    public function test_deregistering_forgets_the_device(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new device_service();
        $service->register($user->id, 'tok-abc', 'android');

        $this->assertTrue($service->deregister($user->id, 'tok-abc'));
        $this->assertSame(0, $DB->count_records('local_pshub_push_token'));
    }

    /**
     * Forgetting a handset we never knew is not an error.
     *
     * Sign-out has to finish. An exception here leaves the app unable to
     * complete signing somebody out of a shared phone, which is the exact
     * moment it matters most.
     */
    public function test_deregistering_an_unknown_token_is_quiet(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse((new device_service())->deregister($user->id, 'tok-never-seen'));
    }

    /**
     * And nobody can forget somebody else's handset.
     *
     * A token is a string: being able to name one is not the same as owning
     * it. Without this check, anyone who learned a token could silence
     * another learner's reminders.
     */
    public function test_nobody_can_deregister_another_persons_device(): void {
        global $DB;
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $service = new device_service();
        $service->register($owner->id, 'tok-abc', 'android');

        $this->assertFalse($service->deregister($stranger->id, 'tok-abc'));
        $this->assertSame(1, $DB->count_records('local_pshub_push_token'));
    }

    /**
     * Forgetting every handset of a person leaves everybody else's alone.
     */
    public function test_forgetting_all_is_per_person(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $service = new device_service();

        $service->register($user->id, 'tok-a', 'android');
        $service->register($user->id, 'tok-b', 'ios');
        $service->register($other->id, 'tok-c', 'android');

        $service->forget_all($user->id);

        $this->assertSame([], $service->get_devices($user->id));
        $this->assertCount(1, $service->get_devices($other->id));
    }

    /**
     * The external function registers against the CALLER, not a parameter.
     *
     * There is deliberately no userid in the signature. With one, any app user
     * could register a handset against somebody else's account and start
     * receiving their timetable.
     */
    public function test_the_external_function_registers_against_the_caller(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = register_device::execute('tok-from-app', 'ios');
        $result = \core_external\external_api::clean_returnvalue(
            register_device::execute_returns(), $result);

        $this->assertTrue($result['registered']);
        $this->assertSame((int) $user->id,
            (int) $DB->get_field('local_pshub_push_token', 'userid', ['token' => 'tok-from-app']));
    }

    /**
     * And the external sign-out only reaches the caller's own handset.
     */
    public function test_the_external_function_cannot_forget_someone_elses(): void {
        global $DB;
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        (new device_service())->register($owner->id, 'tok-abc', 'android');

        $this->setUser($stranger);
        $result = \core_external\external_api::clean_returnvalue(
            deregister_device::execute_returns(), deregister_device::execute('tok-abc'));

        $this->assertFalse($result['removed']);
        $this->assertSame(1, $DB->count_records('local_pshub_push_token'));
    }

    /**
     * The external function refuses a platform we do not build for.
     */
    public function test_the_external_function_refuses_an_unknown_platform(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        register_device::execute('tok-abc', 'symbian');
    }

    /**
     * The service our application uses is NOT the official Moodle app's.
     *
     * Adding our functions to MOODLE_OFFICIAL_MOBILE_SERVICE would hand them
     * to an application we did not write, on every site that installs this
     * plugin.
     */
    public function test_the_app_service_is_our_own_and_ships_disabled(): void {
        global $DB;
        $this->resetAfterTest();

        $service = $DB->get_record('external_services',
            ['shortname' => 'pshub_app', 'component' => 'local_pshub'], '*', MUST_EXIST);

        $this->assertSame(0, (int) $service->enabled, 'a door is opened on purpose');

        $functions = $DB->get_fieldset_select('external_services_functions', 'functionname',
            'externalserviceid = ?', [$service->id]);

        // The rule, not the inventory. Freezing the exact list makes this test
        // fail every time the API grows, which trains people to edit the test
        // instead of reading it - and the thing worth protecting is that the
        // service carries OUR functions and nobody else's.
        $this->assertContains('local_pshub_register_device', $functions);
        $this->assertContains('local_pshub_deregister_device', $functions);
        foreach ($functions as $function) {
            $this->assertStringStartsWith('local_pshub_', $function);
        }

        $official = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        if ($official) {
            $onofficial = $DB->get_fieldset_select('external_services_functions', 'functionname',
                'externalserviceid = ? AND functionname LIKE ?', [$official->id, 'local_pshub%']);
            $this->assertSame([], $onofficial);
        }
    }
}
