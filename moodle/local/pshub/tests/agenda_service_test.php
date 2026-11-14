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

use local_pshub\calendar\agenda_service;
use local_pshub\local\constants;
use local_pshub\session\session_service;

/**
 * The learner's diary: what it shows, and what it refuses to promise.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\calendar\agenda_service
 */
final class agenda_service_test extends \advanced_testcase {

    /**
     * A published, free hub course.
     *
     * Free so these tests are about the diary rather than about payment.
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
            'lang_code' => 'fr',
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
     * A session on a course, written straight in so a test can place it
     * anywhere in time without arguing with the scheduler.
     *
     * @param int $courseid
     * @param int $start
     * @param array $overrides
     * @return int
     */
    private function session(int $courseid, int $start, array $overrides = []): int {
        global $DB;

        $minutes = $overrides['duration_minutes'] ?? 50;

        return (int) $DB->insert_record('local_pshub_session', (object) array_merge([
            'courseid' => $courseid,
            'bbb_cmid' => null,
            'teacherid' => 0,
            'title' => 'Séance',
            'scheduled_start' => $start,
            'scheduled_end' => $start + ($minutes * MINSECS),
            'duration_minutes' => $minutes,
            'status' => constants::SESSION_SCHEDULED,
            'recording_enabled' => 0,
            'timecreated' => $start - WEEKSECS,
            'timemodified' => $start - WEEKSECS,
        ], $overrides));
    }

    /**
     * Somebody with nothing booked gets an empty diary, not an error.
     *
     * The commonest state on the day somebody signs up.
     */
    public function test_a_learner_with_no_courses_has_an_empty_diary(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $this->assertSame([], (new agenda_service())->get_upcoming_days($user->id));
        $this->assertSame(0, (new agenda_service())->count_upcoming($user->id));
    }

    /**
     * Classes are grouped into days, and the days come in order.
     */
    public function test_classes_are_grouped_by_day_in_order(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);

        // Two on the same day, one a week later, deliberately inserted out of
        // order so that any ordering the test sees is the service's.
        $this->session($course->id, $now + WEEKSECS);
        $this->session($course->id, $now + (2 * HOURSECS));
        $this->session($course->id, $now + (5 * HOURSECS));

        $days = (new agenda_service())->get_upcoming_days($user->id, $now);

        $this->assertCount(2, $days);
        $this->assertCount(2, $days[0]['sessions'], 'two classes on the first day');
        $this->assertCount(1, $days[1]['sessions']);
        $this->assertTrue($days[0]['dayiso'] < $days[1]['dayiso'], 'days in order');
        $this->assertSame(3, (new agenda_service())->count_upcoming($user->id, $now));
    }

    /**
     * The day a template prints is a real ISO date.
     *
     * A regression test with a Windows reason: userdate('%Y-%m-%d') hands back
     * "2026-11-7" there, because Moodle strips the day's leading zero to paper
     * over a platform difference. That string is not a date to a browser, and
     * `<time datetime>` is the one place it has to be.
     */
    public function test_the_machine_readable_day_is_a_valid_iso_date(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 11, 1, 9, 0);
        // The seventh of the month: one digit, which is where it breaks.
        $this->session($course->id, make_timestamp(2026, 11, 7, 18, 0));

        $days = (new agenda_service())->get_upcoming_days($user->id, $now);

        $this->assertCount(1, $days);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $days[0]['dayiso']);
        $this->assertSame('2026-11-07', $days[0]['dayiso']);
    }

    /**
     * Today says "today", and tomorrow says "tomorrow".
     *
     * A learner reading a date has to work out whether it is today. The point
     * of a diary is that they do not have to.
     */
    public function test_today_and_tomorrow_are_named(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Mid-morning, so "+ 4 hours" cannot spill into tomorrow.
        $now = make_timestamp(2026, 10, 11, 9, 0);
        $this->session($course->id, $now + (4 * HOURSECS));
        $this->session($course->id, $now + DAYSECS + (4 * HOURSECS));
        $this->session($course->id, $now + (10 * DAYSECS));

        $days = (new agenda_service())->get_upcoming_days($user->id, $now);

        $this->assertCount(3, $days);
        $this->assertSame(get_string('agenda_today', 'local_pshub'), $days[0]['daylabel']);
        $this->assertTrue($days[0]['istoday']);
        $this->assertSame(get_string('agenda_tomorrow', 'local_pshub'), $days[1]['daylabel']);
        $this->assertFalse($days[1]['istoday']);
        $this->assertNotSame(get_string('agenda_tomorrow', 'local_pshub'), $days[2]['daylabel'],
            'a day further out is written out, not called tomorrow');
    }

    /**
     * A cancelled class is not an appointment.
     *
     * A diary that keeps showing one is a diary somebody stops reading.
     */
    public function test_a_cancelled_class_is_not_in_the_diary(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);
        $this->session($course->id, $now + DAYSECS,
            ['status' => constants::SESSION_CANCELLED]);

        $this->assertSame([], (new agenda_service())->get_upcoming_days($user->id, $now));
    }

    /**
     * Somebody else's course is not in my diary.
     *
     * The query is driven by enrolment, so this is the test that it is.
     */
    public function test_a_course_the_learner_is_not_in_is_absent(): void {
        $this->resetAfterTest();

        $mine = $this->hub_course();
        $theirs = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $mine->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);
        $this->session($mine->id, $now + DAYSECS);
        $this->session($theirs->id, $now + DAYSECS);

        $days = (new agenda_service())->get_upcoming_days($user->id, $now);

        $this->assertCount(1, $days);
        $this->assertCount(1, $days[0]['sessions']);
        $this->assertSame((int) $mine->id, $days[0]['sessions'][0]['courseid']);
    }

    /**
     * A whole twelve-week course fits inside the horizon.
     *
     * The horizon was sixty days first, which silently hid the second half of
     * every term - the half a learner is least sure of.
     */
    public function test_the_horizon_covers_a_full_term(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);
        for ($week = 1; $week <= 12; $week++) {
            $this->session($course->id, $now + ($week * WEEKSECS));
        }

        $this->assertSame(12, (new agenda_service())->count_upcoming($user->id, $now));
    }

    /**
     * Beyond the horizon, nothing.
     */
    public function test_a_class_past_the_horizon_is_not_shown(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);
        $this->session($course->id, $now + ((agenda_service::HORIZON_DAYS + 5) * DAYSECS));

        $this->assertSame([], (new agenda_service())->get_upcoming_days($user->id, $now));
    }

    /**
     * THE RULE THIS WHOLE CLASS EXISTS FOR: the diary never offers a door the
     * door refuses.
     *
     * A class inside the join window, on a course the learner has access to,
     * but with no room attached. The verdict is "yes" and there is still
     * nowhere to walk into - so the row offers no join, and says why.
     */
    public function test_an_open_verdict_with_no_room_offers_no_door(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 18, 0);
        // Five minutes in: well inside the window, no bbb_cmid.
        $this->session($course->id, $now - (5 * MINSECS), ['status' => constants::SESSION_LIVE]);

        $days = (new agenda_service())->get_upcoming_days($user->id, $now);
        $row = $days[0]['sessions'][0];

        $this->assertFalse($row['canjoin'], 'no room means no button');
        $this->assertSame(get_string('agenda_noroom', 'local_pshub'), $row['note']);
        $this->assertTrue($row['islive']);
    }

    /**
     * And with a room, inside the window, it does offer the door.
     *
     * The other half of the same rule: the diary must not be uselessly timid
     * either.
     */
    public function test_a_room_inside_the_window_offers_the_door(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 18, 0);
        $this->session($course->id, $now - (5 * MINSECS), [
            'status' => constants::SESSION_LIVE,
            'bbb_cmid' => 424242,
        ]);

        $row = (new agenda_service())->get_upcoming_days($user->id, $now)[0]['sessions'][0];

        $this->assertTrue($row['canjoin']);
        $this->assertNull($row['note'], 'an open door needs no explanation');
    }

    /**
     * A class still hours away is not joinable, and is not apologised for.
     *
     * "Too early" is the normal state of almost every row in a diary. Printing
     * a sentence about it on each one turns the page into a wall of refusals
     * when the time is already in the row.
     */
    public function test_a_class_hours_away_is_quiet_about_it(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);
        $this->session($course->id, $now + (6 * HOURSECS), ['bbb_cmid' => 424242]);

        $row = (new agenda_service())->get_upcoming_days($user->id, $now)[0]['sessions'][0];

        $this->assertFalse($row['canjoin']);
        $this->assertNull($row['note']);
    }

    /**
     * The verdict the diary shows is the door's own.
     *
     * Proved by taking the access away: an unenrolled learner is not in the
     * query at all, so the test revokes access while leaving the enrolment,
     * which is the state a lapsed payment produces.
     */
    public function test_a_lapsed_access_shows_the_doors_own_reason(): void {
        global $DB;
        $this->resetAfterTest();

        // Paid, so that access is a thing that can be absent.
        $course = $this->hub_course(['access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 15000]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 18, 0);
        $this->session($course->id, $now - (5 * MINSECS), [
            'status' => constants::SESSION_LIVE,
            'bbb_cmid' => 424242,
        ]);

        $row = (new agenda_service())->get_upcoming_days($user->id, $now)[0]['sessions'][0];

        $this->assertFalse($row['canjoin'], 'enrolled is not the same as paid for');
        $this->assertNotNull($row['note'], 'and the learner is told which it is');
        $this->assertSame(get_string('join_noaccess', 'local_pshub'), $row['note']);
    }

    /**
     * What has just been, newest day first and with no doors.
     */
    public function test_recent_classes_are_listed_newest_day_first(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 12, 0);
        $this->session($course->id, $now - (2 * DAYSECS), ['status' => constants::SESSION_ENDED]);
        $this->session($course->id, $now - DAYSECS, ['status' => constants::SESSION_ENDED]);

        $days = (new agenda_service())->get_recent_days($user->id, $now);

        $this->assertCount(2, $days);
        $this->assertTrue($days[0]['dayiso'] > $days[1]['dayiso'], 'newest day first');
        foreach ($days as $day) {
            foreach ($day['sessions'] as $row) {
                $this->assertFalse($row['canjoin'], 'a class that is over has no door');
                $this->assertTrue($row['isended']);
            }
        }
    }

    /**
     * Yesterday says "yesterday".
     */
    public function test_yesterday_is_named(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 12, 0);
        $this->session($course->id, $now - DAYSECS, ['status' => constants::SESSION_ENDED]);

        $days = (new agenda_service())->get_recent_days($user->id, $now);

        $this->assertSame(get_string('agenda_yesterday', 'local_pshub'), $days[0]['daylabel']);
    }

    /**
     * A class that is over is not in the upcoming list.
     */
    public function test_a_finished_class_is_not_upcoming(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 12, 0);
        $this->session($course->id, $now - DAYSECS, ['status' => constants::SESSION_ENDED]);

        $this->assertSame([], (new agenda_service())->get_upcoming_days($user->id, $now));
    }

    /**
     * The count is a count, not the length of the list beside it.
     *
     * A regression test for a lie on the dashboard: the tile called "upcoming
     * live sessions" counted the list it was about to print, and that list is
     * the next five. A learner with eleven classes left was told they had
     * five - and nothing looked broken, which is why it survived.
     */
    public function test_the_count_is_not_capped_like_the_list(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $now = make_timestamp(2026, 10, 11, 9, 0);
        for ($week = 1; $week <= 11; $week++) {
            $this->session($course->id, $now + ($week * WEEKSECS));
        }

        // What the dashboard used to count, and what it counts now.
        $list = (new \local_pshub\learner\learner_service())->get_upcoming_sessions($user->id);
        $this->assertCount(5, $list, 'the list is still the next five');
        $this->assertSame(11, (new agenda_service())->count_upcoming($user->id, $now));
    }

    /**
     * The subscription URL exists, and carries a token.
     */
    public function test_the_export_url_carries_a_token(): void {
        $this->resetAfterTest();

        set_config('enablecalendarexport', 1);
        $user = $this->getDataGenerator()->create_user();

        $url = (new agenda_service())->get_export_url($user->id);

        $this->assertNotNull($url);
        $this->assertNotEmpty($url->param('authtoken'));
        $this->assertSame((string) $user->id, (string) $url->param('userid'));
    }

    /**
     * With export switched off site-wide, there is no link rather than a
     * broken one.
     */
    public function test_no_export_url_when_the_site_has_export_off(): void {
        $this->resetAfterTest();

        set_config('enablecalendarexport', 0);
        $user = $this->getDataGenerator()->create_user();

        $this->assertNull((new agenda_service())->get_export_url($user->id));
    }

    /**
     * The repair path puts a drifted calendar entry back on the session.
     *
     * The drift is real and this is how it happened here: a script moved a
     * session with a direct database write, which is the one way to change an
     * appointment without the service noticing.
     */
    public function test_resync_calendar_repairs_a_drifted_entry(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();

        $start = make_timestamp(2026, 11, 7, 18, 0);
        $service = new session_service();
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        // Move it behind the service's back, the way a script or a restore can.
        $moved = $start + WEEKSECS;
        $DB->set_field('local_pshub_session', 'scheduled_start', $moved, ['id' => $id]);
        $DB->set_field('local_pshub_session', 'scheduled_end', $moved + (50 * MINSECS),
            ['id' => $id]);

        $event = $DB->get_record('event', ['component' => 'local_pshub', 'instance' => $id]);
        $this->assertNotFalse($event);
        $this->assertSame($start, (int) $event->timestart, 'the entry still has the old time');

        $service->resync_calendar($id);

        $event = $DB->get_record('event', ['component' => 'local_pshub', 'instance' => $id]);
        $this->assertSame($moved, (int) $event->timestart);
    }
}
