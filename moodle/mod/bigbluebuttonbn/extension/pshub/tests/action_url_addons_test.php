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

namespace bbbext_pshub;

use bbbext_pshub\bigbluebuttonbn\action_url_addons;

/**
 * The one parameter this subplugin exists to add.
 *
 * @package    bbbext_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \bbbext_pshub\bigbluebuttonbn\action_url_addons
 */
final class action_url_addons_test extends \advanced_testcase {

    /**
     * `duration` is added when a meeting is created.
     */
    public function test_a_meeting_is_created_with_a_duration(): void {
        $this->resetAfterTest();
        set_config('sessioncap', 50 * MINSECS, 'local_pshub');

        $result = (new action_url_addons())->execute('create', [], [], null);

        $this->assertSame(50, $result['data']['duration']);
    }

    /**
     * And on nothing else.
     *
     * `join` and `end` are signed URLs too; an unexpected parameter in one is
     * noise at best and a rejected call at worst.
     */
    public function test_no_other_action_is_touched(): void {
        $this->resetAfterTest();
        set_config('sessioncap', 50 * MINSECS, 'local_pshub');

        $addons = new action_url_addons();

        foreach (['join', 'end', 'getMeetingInfo', 'getRecordings'] as $action) {
            $result = $addons->execute($action, [], [], null);
            $this->assertSame([], $result['data'], $action . ' must add nothing');
        }
    }

    /**
     * The cap is read as SECONDS, which is how the setting stores it.
     *
     * Reading it as minutes gives a duration of 3000 and a cap that never
     * bites - the bug this test exists to keep out.
     */
    public function test_the_cap_is_read_in_seconds(): void {
        $this->resetAfterTest();
        set_config('sessioncap', 25 * MINSECS, 'local_pshub');

        $result = (new action_url_addons())->execute('create', [], [], null);

        $this->assertSame(25, $result['data']['duration']);
    }

    /**
     * A session the hub knows about imposes its own length, not the site cap.
     *
     * A forty-five minute class should end at forty-five, not at fifty.
     */
    public function test_a_known_session_imposes_its_own_length(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('sessioncap', 50 * MINSECS, 'local_pshub');

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('bigbluebuttonbn',
            ['course' => $course->id]);

        $sessionid = $DB->insert_record('local_pshub_session', (object) [
            'courseid' => $course->id,
            'bbb_cmid' => $activity->cmid,
            'teacherid' => 0,
            'title' => 'Séance',
            'scheduled_start' => time() + DAYSECS,
            'scheduled_end' => time() + DAYSECS + (45 * MINSECS),
            'duration_minutes' => 45,
            'status' => 'scheduled',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = (new action_url_addons())->execute('create', [], [], (int) $activity->id);

        $this->assertSame(45, $result['data']['duration']);
        $this->assertSame((string) $sessionid, $result['metadata']['pshub-sessionid'],
            'the meeting carries the session it belongs to, so recordings and '
            . 'attendance can be matched back without guessing');
    }

    /**
     * A BigBlueButton activity the hub does not know still gets the site cap.
     *
     * Deliberate: §8.3 promises that no live class on this platform runs past
     * the cap, not merely the ones created through our own scheduling page.
     */
    public function test_an_unknown_meeting_still_gets_the_site_cap(): void {
        $this->resetAfterTest();
        set_config('sessioncap', 50 * MINSECS, 'local_pshub');

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('bigbluebuttonbn',
            ['course' => $course->id]);

        $result = (new action_url_addons())->execute('create', [], [], (int) $activity->id);

        $this->assertSame(50, $result['data']['duration']);
        $this->assertArrayNotHasKey('pshub-sessionid', $result['metadata']);
    }

    /**
     * With no cap configured at all, nothing is added.
     *
     * Sending `duration=0` would mean "no limit" to BigBlueButton, which is
     * the opposite of what an unset cap should imply - better to leave the
     * parameter out and let the other two layers answer.
     */
    public function test_no_cap_means_no_parameter(): void {
        $this->resetAfterTest();
        set_config('sessioncap', 0, 'local_pshub');

        $result = (new action_url_addons())->execute('create', [], [], null);

        $this->assertSame([], $result['data']);
    }
}
