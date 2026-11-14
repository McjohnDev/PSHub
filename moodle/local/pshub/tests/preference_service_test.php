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

use local_pshub\notification\preference_service;

/**
 * Notification preferences in sentences, and whether they actually bite.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\notification\preference_service
 */
final class preference_service_test extends \advanced_testcase {

    /**
     * The provider names this person is offered.
     *
     * @param \stdClass $user
     * @return string[]
     */
    private function offered(\stdClass $user): array {
        return array_column((new preference_service())->get_settings($user), 'provider');
    }

    /**
     * Send one session reminder, for real, through the processors.
     *
     * @param \stdClass $to
     * @return void
     */
    private function remind(\stdClass $to): void {
        $message = new \core\message\message();
        $message->component = 'local_pshub';
        $message->name = 'sessionreminder';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $to;
        $message->subject = 'Rappel';
        $message->fullmessage = 'Rappel';
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>Rappel</p>';
        $message->smallmessage = 'Rappel';
        $message->notification = 1;

        message_send($message);
    }

    /**
     * A learner is asked about the one thing they can receive.
     *
     * The screen's whole claim is that it asks fewer questions than Moodle's.
     * It only does so because each provider declares who it is for.
     */
    public function test_a_learner_is_only_asked_about_their_own_notifications(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();

        $this->assertSame(['sessionreminder'], $this->offered($learner));
    }

    /**
     * Somebody who can submit a course is also asked about decisions on one.
     */
    public function test_a_teacher_is_asked_about_decisions_too(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/pshub:submitcourse', CAP_ALLOW, $roleid,
            \context_system::instance()->id);
        role_assign($roleid, $teacher->id, \context_system::instance()->id);

        $offered = $this->offered($teacher);

        $this->assertContains('coursereviewed', $offered);
        $this->assertContains('sessionreminder', $offered);
        $this->assertNotContains('coursesubmitted', $offered,
            'a teacher does not review other people\'s courses');
    }

    /**
     * And a reviewer is asked about the queue.
     */
    public function test_a_reviewer_is_asked_about_the_queue(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $admin = \core_user::get_user(get_admin()->id);

        $this->assertContains('coursesubmitted', $this->offered($admin));
    }

    /**
     * Somebody who has never been here sees the site's defaults, ticked.
     *
     * Reading only the user's own preference would show every box empty to a
     * person who does receive these messages - a lie on the screen, and one
     * that invites them to tick a box that was already on.
     */
    public function test_an_untouched_account_shows_the_site_defaults(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();

        $on = (new preference_service())->enabled_channels($learner, 'sessionreminder');

        $this->assertContains('email', $on);
        $this->assertContains('popup', $on);
    }

    /**
     * A form post is turned into provider and channel.
     */
    public function test_the_form_is_read_into_providers_and_channels(): void {
        $this->resetAfterTest();

        $chosen = (new preference_service())->read_form([
            'sesskey' => 'x',
            'save' => '1',
            'pref_sessionreminder_popup' => '1',
            'pref_coursereviewed_email' => '1',
            'pref_coursereviewed_popup' => '0',
        ]);

        $this->assertSame(['popup'], $chosen['sessionreminder']);
        $this->assertSame(['email'], $chosen['coursereviewed'],
            'an unticked box posts nothing, or posts a zero');
    }

    /**
     * Unticking a channel writes Moodle's own preference, in Moodle's format.
     */
    public function test_saving_writes_moodles_preference(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();

        (new preference_service())->save($learner, ['sessionreminder' => ['popup']]);

        $this->assertSame('popup', get_user_preferences(
            'message_provider_local_pshub_sessionreminder_enabled', null, $learner));
    }

    /**
     * Unticking everything is a choice, and is stored as one.
     *
     * 'none' rather than an empty string: that is what Moodle reads.
     */
    public function test_unticking_everything_is_stored_as_none(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();

        (new preference_service())->save($learner, []);

        $this->assertSame('none', get_user_preferences(
            'message_provider_local_pshub_sessionreminder_enabled', null, $learner));
        $this->assertSame([], (new preference_service())->enabled_channels($learner,
            'sessionreminder'));
    }

    /**
     * A posted field is not a permission.
     *
     * A learner posting `pref_coursesubmitted_email` is posting about a
     * notification they are not offered. Writing it would be harmless today
     * and is exactly the habit that stops being harmless later.
     */
    public function test_a_provider_the_person_is_not_offered_is_ignored(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();

        (new preference_service())->save($learner, [
            'sessionreminder' => ['popup'],
            'coursesubmitted' => ['email'],
        ]);

        $this->assertNull(get_user_preferences(
            'message_provider_local_pshub_coursesubmitted_enabled', null, $learner));
    }

    /**
     * Nor is an invented channel.
     */
    public function test_a_channel_that_does_not_exist_is_ignored(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();

        (new preference_service())->save($learner, [
            'sessionreminder' => ['popup', 'carrierpigeon'],
        ]);

        $this->assertSame('popup', get_user_preferences(
            'message_provider_local_pshub_sessionreminder_enabled', null, $learner));
    }

    /**
     * A channel the site has switched off is not offered.
     */
    public function test_a_disabled_processor_is_not_offered(): void {
        global $DB;
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $DB->set_field('message_processors', 'enabled', 0, ['name' => 'email']);
        \core_message\api::update_processor_status(
            $DB->get_record('message_processors', ['name' => 'email']), 0);

        $names = array_column((new preference_service())->get_channels($learner), 'name');

        $this->assertNotContains('email', $names);
        $this->assertContains('popup', $names);
    }

    /**
     * THE TEST THAT MATTERS: the setting changes where the message goes.
     *
     * A preferences screen that writes a row and changes nothing is worse than
     * none, because the person believes they have been heard.
     */
    public function test_turning_email_off_stops_the_email(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $quiet = $this->getDataGenerator()->create_user();
        $normal = $this->getDataGenerator()->create_user();

        (new preference_service())->save($quiet, ['sessionreminder' => ['popup']]);

        $sink = $this->redirectEmails();
        $this->remind($quiet);
        $this->assertCount(0, $sink->get_messages(), 'e-mail was turned off');

        $this->remind($normal);
        $this->assertCount(1, $sink->get_messages(), 'and nobody else was touched');
        $sink->close();
    }

    /**
     * And the in-app message still arrives, which is what "popup only" means.
     */
    public function test_turning_email_off_leaves_the_in_app_message(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $quiet = $this->getDataGenerator()->create_user();
        (new preference_service())->save($quiet, ['sessionreminder' => ['popup']]);

        $before = $DB->count_records('message_popup_notifications');
        $sink = $this->redirectEmails();
        $this->remind($quiet);
        $sink->close();

        $this->assertSame($before + 1, $DB->count_records('message_popup_notifications'));
    }

    /**
     * "Send me nothing" means nothing, not "no e-mail".
     *
     * Moodle's field is called `emailstop`, and it is checked before EVERY
     * processor. A screen that offered it as an e-mail switch would be
     * describing something else.
     */
    public function test_the_account_switch_silences_every_channel(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $user = $this->getDataGenerator()->create_user();
        $service = new preference_service();

        // Everything ticked, and then silenced.
        $service->save($user, ['sessionreminder' => ['popup', 'email']]);
        $service->set_silenced($user, true);

        $this->assertTrue($service->is_silenced($user));
        $this->assertSame(1, (int) $DB->get_field('user', 'emailstop', ['id' => $user->id]));

        $beforepopup = $DB->count_records('message_popup_notifications');
        $sink = $this->redirectEmails();
        $this->remind($DB->get_record('user', ['id' => $user->id]));
        $sink->close();

        $this->assertCount(0, $sink->get_messages());
        $this->assertSame($beforepopup, $DB->count_records('message_popup_notifications'),
            'not the in-app one either');
    }

    /**
     * And it can be undone, with the ticks still where they were.
     */
    public function test_the_account_switch_can_be_lifted(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new preference_service();

        $service->save($user, ['sessionreminder' => ['popup']]);
        $service->set_silenced($user, true);
        $service->set_silenced($user, false);

        $this->assertFalse($service->is_silenced($user));
        $this->assertSame(['popup'], $service->enabled_channels($user, 'sessionreminder'));
    }

    /**
     * Channels are named for the person, not for the code that delivers them.
     */
    public function test_channels_are_named_in_plain_language(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        foreach ((new preference_service())->get_channels($user) as $channel) {
            $this->assertNotSame($channel['name'], $channel['label'],
                'nobody outside this repository has heard of "popup"');
            $this->assertNotEmpty($channel['label']);
        }
    }

    /**
     * Every notification offered has a sentence of its own.
     *
     * A missing string shows as [[notif_x]] on the page, which is the one
     * thing this screen exists to avoid.
     */
    public function test_every_offered_notification_is_written_out(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $admin = \core_user::get_user(get_admin()->id);

        foreach ((new preference_service())->get_settings($admin) as $setting) {
            $this->assertStringNotContainsString('[[', $setting['title'], $setting['provider']);
            $this->assertStringNotContainsString('[[', $setting['description'],
                $setting['provider']);
        }
    }
}
