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

use core_external\external_api;
use local_pshub\external\get_agenda;
use local_pshub\external\get_my_courses;
use local_pshub\local\constants;

/**
 * What our own application is allowed to read, and in what shape.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\external\get_agenda
 * @covers     \local_pshub\external\get_my_courses
 */
final class app_api_test extends \advanced_testcase {

    /**
     * A published hub course with a Moodle course behind it.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function hub_course(array $overrides = []): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) array_merge([
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'lang_code' => 'en',
            'cefr_level' => 'A2',
            'access_type' => constants::ACCESS_TYPE_FREE,
            'price_amount' => 0,
            'price_currency' => 'XAF',
            'review_status' => constants::REVIEW_PUBLISHED,
            'is_featured' => 0,
            'featured_order' => 0,
            'session_duration_minutes' => 50,
            'max_participants' => 8,
            'is_one_to_one' => 0,
            'audience' => 'adult',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides));

        return $course;
    }

    /**
     * A class on a course.
     *
     * @param int $courseid
     * @param int $start
     * @param array $overrides
     * @return int
     */
    private function session(int $courseid, int $start, array $overrides = []): int {
        global $DB;

        return (int) $DB->insert_record('local_pshub_session', (object) array_merge([
            'courseid' => $courseid,
            'bbb_cmid' => null,
            'teacherid' => 0,
            'title' => 'Séance',
            'scheduled_start' => $start,
            'scheduled_end' => $start + (50 * MINSECS),
            'duration_minutes' => 50,
            'status' => constants::SESSION_SCHEDULED,
            'recording_enabled' => 0,
            'timecreated' => $start - WEEKSECS,
            'timemodified' => $start - WEEKSECS,
        ], $overrides));
    }

    /**
     * Run get_agenda and check the answer against its own contract.
     *
     * clean_returnvalue is the point: it fails when the structure and the data
     * disagree, which is the mistake a client finds at runtime and a test
     * finds here.
     *
     * @param bool $includerecent
     * @return array
     */
    private function agenda(bool $includerecent = false): array {
        return external_api::clean_returnvalue(
            get_agenda::execute_returns(), get_agenda::execute($includerecent));
    }

    /**
     * An empty diary is a shape, not an absence.
     *
     * A client that has to handle `null` as well as `[]` will handle one of
     * them wrong.
     */
    public function test_an_empty_diary_still_has_the_full_shape(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $result = $this->agenda(true);

        $this->assertSame([], $result['upcoming']);
        $this->assertSame([], $result['recent']);
        $this->assertGreaterThan(0, $result['servertime']);
        $this->assertNotEmpty($result['timezone']);
    }

    /**
     * The diary comes back as days, each with its classes.
     */
    public function test_the_diary_returns_days_and_classes(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $this->session($course->id, time() + DAYSECS);

        $result = $this->agenda();

        $this->assertCount(1, $result['upcoming']);
        $this->assertCount(1, $result['upcoming'][0]['sessions']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/',
            $result['upcoming'][0]['dayiso']);
    }

    /**
     * The server's clock travels with the answer.
     *
     * The device clock is wrong often on the handsets this product targets.
     * The app shows this; it does not correct it.
     */
    public function test_the_server_clock_and_zone_travel_with_the_answer(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        set_config('timezone', 'Africa/Douala');
        \core_date::set_default_server_timezone();

        $result = $this->agenda();

        $this->assertSame('Africa/Douala', $result['timezone']);
        $this->assertEqualsWithDelta(time(), $result['servertime'], 5);
    }

    /**
     * THE RULE: the verdict is the server's, and it is in the payload.
     *
     * A class inside the join window, with a room, on a course the caller has
     * access to - `canjoin` is true, and the client never had to look at a
     * clock to find out.
     */
    public function test_the_verdict_is_in_the_payload(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $this->session($course->id, time() - (5 * MINSECS), [
            'status' => constants::SESSION_LIVE,
            'bbb_cmid' => 424242,
        ]);

        $row = $this->agenda()['upcoming'][0]['sessions'][0];

        $this->assertTrue($row['canjoin']);
        $this->assertSame('ok', $row['state']);
        $this->assertTrue($row['islive']);
    }

    /**
     * And it is false, with a reason, when the door is shut.
     */
    public function test_a_shut_door_comes_back_with_its_reason(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(['access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 15000]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $this->session($course->id, time() - (5 * MINSECS), [
            'status' => constants::SESSION_LIVE,
            'bbb_cmid' => 424242,
        ]);

        $row = $this->agenda()['upcoming'][0]['sessions'][0];

        $this->assertFalse($row['canjoin']);
        $this->assertSame('noaccess', $row['state']);
        $this->assertNotEmpty($row['note']);
    }

    /**
     * Recent classes only when asked for.
     *
     * They cost a second query, and the home screen does not need them.
     */
    public function test_recent_classes_only_when_asked_for(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $this->session($course->id, time() - DAYSECS, ['status' => constants::SESSION_ENDED]);

        $this->assertSame([], $this->agenda(false)['recent']);
        $this->assertCount(1, $this->agenda(true)['recent']);
    }

    /**
     * The diary is the caller's, and nobody else's.
     */
    public function test_the_diary_is_the_callers_own(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $mine = $this->getDataGenerator()->create_user();
        $theirs = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($theirs->id, $course->id);
        $this->session($course->id, time() + DAYSECS);

        $this->setUser($mine);

        $this->assertSame([], $this->agenda()['upcoming']);
    }

    /**
     * The course list comes back in the shape the contract promises.
     */
    public function test_the_course_list_matches_its_contract(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $result = external_api::clean_returnvalue(
            get_my_courses::execute_returns(), get_my_courses::execute());

        $this->assertCount(1, $result['courses']);
        $this->assertSame((int) $course->id, $result['courses'][0]['courseid']);
    }

    /**
     * A free course says it is free, not that it costs nothing.
     *
     * A regression test for a trap the web never fell into, because every
     * template branches on `isfree` before printing a price. A client that
     * trusts a field called `priceformatted` would have shipped "0 FCFA" on
     * every free course, and only a learner would have found it.
     */
    public function test_a_free_course_says_free_rather_than_zero(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $row = external_api::clean_returnvalue(
            get_my_courses::execute_returns(), get_my_courses::execute())['courses'][0];

        $this->assertTrue($row['isfree']);
        $this->assertSame(get_string('card_free', 'local_pshub'), $row['priceformatted']);
        $this->assertStringNotContainsString('0', $row['priceformatted']);
    }

    /**
     * A paid course still carries its figure, formatted.
     *
     * XAF has no subunit, and a client that divides by 100 produces a wrong
     * price in a currency its author has probably never handled - which is
     * why the formatted string is in the payload at all.
     */
    public function test_a_paid_course_carries_its_figure(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(['access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 15000]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $row = external_api::clean_returnvalue(
            get_my_courses::execute_returns(), get_my_courses::execute())['courses'][0];

        $this->assertFalse($row['isfree']);
        $this->assertSame(15000, $row['price']);
        $this->assertSame('XAF', $row['currency']);
        $this->assertStringContainsString('15', $row['priceformatted']);
    }

    /**
     * The API hands over data, not a web page's context.
     *
     * `cardtone` picks one of four card backgrounds; `langplate` is the
     * two-letter plate drawn when a course has no photograph; `ctalabel` is
     * the words on a web button. They are answers to questions the web pages
     * ask, and a field we publish is a field we can never change.
     */
    public function test_no_web_template_fields_leak_into_the_api(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $row = external_api::clean_returnvalue(
            get_my_courses::execute_returns(), get_my_courses::execute())['courses'][0];

        foreach (['cardtone', 'langplate', 'ctalabel', 'topbadge', 'hideprice'] as $webonly) {
            $this->assertArrayNotHasKey($webonly, $row);
        }
    }

    /**
     * Somebody taking nothing gets an empty list, not an error.
     */
    public function test_a_learner_with_no_courses_gets_an_empty_list(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $result = external_api::clean_returnvalue(
            get_my_courses::execute_returns(), get_my_courses::execute());

        $this->assertSame([], $result['courses']);
    }
}
