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

use local_pshub\learner\learner_service;
use local_pshub\local\constants;

/**
 * What a learner's own space shows them.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\learner\learner_service
 */
final class learner_service_test extends \advanced_testcase {

    /**
     * A published hub course, with a Moodle course behind it.
     *
     * @param array $overrides
     * @return \stdClass The Moodle course.
     */
    private function hub_course(array $overrides = []): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) array_merge([
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'lang_code' => 'en',
            'cefr_level' => 'B1',
            'access_type' => constants::ACCESS_TYPE_FREE,
            'price_amount' => 0,
            'price_currency' => 'XAF',
            'review_status' => constants::REVIEW_PUBLISHED,
            'is_featured' => 0,
            'featured_order' => 0,
            'session_duration_minutes' => 50,
            'is_one_to_one' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides));

        return $course;
    }

    /**
     * The dashboard lists the hub courses a learner is enrolled in.
     */
    public function test_it_lists_the_courses_they_are_enrolled_in(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $mine = $this->hub_course();
        $theirs = $this->hub_course();

        $this->getDataGenerator()->enrol_user($user->id, $mine->id);

        $courses = (new learner_service())->get_courses($user->id);

        $this->assertCount(1, $courses);
        $this->assertEquals($mine->id, $courses[0]->courseid);
        $this->assertNotEquals($theirs->id, $courses[0]->courseid);
    }

    /**
     * A course an administrator set up directly in Moodle is not part of the
     * product, and the learner's space is the product's - not a mirror of the
     * LMS.
     */
    public function test_a_course_outside_the_hub_is_not_listed(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plain = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $plain->id);

        $this->assertSame([], (new learner_service())->get_courses($user->id));
    }

    /**
     * An unpublished course disappears from the learner's space too.
     *
     * AC-1 is usually read as "not in the catalogue". It also has to hold here:
     * a course withdrawn for review should stop being offered, whoever is
     * looking.
     */
    public function test_an_unpublished_course_is_not_listed(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course(['review_status' => constants::REVIEW_PENDING]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->assertSame([], (new learner_service())->get_courses($user->id));
    }

    /**
     * The card in the learner's space opens the course rather than selling it.
     */
    public function test_the_card_opens_the_learner_course_page(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $card = (new learner_service())->get_courses($user->id)[0];

        // Not /course/view.php: that page would only redirect here, and a
        // redirect is a second round trip on a connection that cannot spare
        // one.
        $this->assertStringContainsString('/local/pshub/learn/course.php', $card->url);
        $this->assertNotEmpty($card->ctalabel);
    }

    /**
     * Upcoming sessions: only this learner's courses, only still to come, and
     * soonest first.
     */
    public function test_upcoming_sessions_are_theirs_and_in_order(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $mine = $this->hub_course();
        $theirs = $this->hub_course();
        $this->getDataGenerator()->enrol_user($user->id, $mine->id);

        $session = function(int $courseid, int $start, string $status) use ($teacher, $DB): int {
            return (int) $DB->insert_record('local_pshub_session', (object) [
                'courseid' => $courseid,
                'teacherid' => $teacher->id,
                'title' => 'Session',
                'scheduled_start' => $start,
                'scheduled_end' => $start + 3000,
                'duration_minutes' => 50,
                'status' => $status,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        };

        $soon = $session($mine->id, time() + DAYSECS, constants::SESSION_SCHEDULED);
        $later = $session($mine->id, time() + (2 * DAYSECS), constants::SESSION_SCHEDULED);
        $session($mine->id, time() - (2 * DAYSECS), constants::SESSION_SCHEDULED);      // Past.
        $session($mine->id, time() + DAYSECS, constants::SESSION_CANCELLED);            // Called off.
        $session($theirs->id, time() + DAYSECS, constants::SESSION_SCHEDULED);          // Someone else's.

        $sessions = (new learner_service())->get_upcoming_sessions($user->id);

        $this->assertCount(2, $sessions);
        $this->assertEquals($soon, $sessions[0]->id, 'the nearest session comes first');
        $this->assertEquals($later, $sessions[1]->id);
    }

    /**
     * Points are summed from the ledger, and none is a real answer.
     */
    public function test_xp_is_summed_from_the_ledger(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new learner_service();

        $this->assertSame(0, $service->get_xp($user->id));

        // Each award names the thing it was earned for. The ledger's unique
        // index is what stops the same session paying out twice (AC-13), so
        // three awards mean three different items - which is also how they
        // arrive in life: three sessions attended.
        foreach ([50, 20, 30] as $item => $points) {
            $DB->insert_record('local_pshub_xp_ledger', (object) [
                'userid' => $user->id,
                'event_key' => constants::XP_LIVE_ATTENDANCE,
                'points' => $points,
                'contextid' => \context_system::instance()->id,
                'itemid' => $item + 1,
                'timecreated' => time(),
            ]);
        }

        $this->assertSame(100, $service->get_xp($user->id));
    }

    /**
     * Someone with nothing yet gets empty answers, not errors.
     *
     * This is the state every learner is in on the day they sign up, and the
     * dashboard has to render it.
     */
    public function test_a_brand_new_learner_has_nothing_and_that_is_fine(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $service = new learner_service();

        $this->assertSame([], $service->get_courses($user->id));
        $this->assertSame([], $service->get_upcoming_sessions($user->id));
        $this->assertSame(0, $service->get_xp($user->id));
    }

    /**
     * A session row on a course, for the detail tests below.
     *
     * @param int $courseid
     * @param int $start
     * @param string $status
     * @param int $bbbcmid
     * @return int
     */
    private function session_on(int $courseid, int $start, string $status, int $bbbcmid = 0): int {
        global $DB;

        return (int) $DB->insert_record('local_pshub_session', (object) [
            'courseid' => $courseid,
            'teacherid' => 0,
            'bbb_cmid' => $bbbcmid ?: null,
            'title' => 'Séance',
            'scheduled_start' => $start,
            'scheduled_end' => $start + 3000,
            'duration_minutes' => 50,
            'status' => $status,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A Moodle course the hub does not publish has no learner page.
     *
     * The page describes a catalogue entry - price, level, sessions - and a
     * course that is not in the catalogue has none of them.
     */
    public function test_there_is_no_detail_for_a_course_outside_the_hub(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse((new learner_service())->get_course_detail($user->id, $course->id));
    }

    /**
     * The teachers named are the ones Moodle records as teaching.
     *
     * Not local_pshub_teacher_assign, which holds the admin's brief about what
     * a course must cover - a different question with a different answer.
     */
    public function test_it_names_the_teachers_moodle_records(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Adèle', 'lastname' => 'Ngo']);
        $course = $this->hub_course();

        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $this->assertCount(1, $detail->teachers);
        $this->assertStringContainsString('Ngo', $detail->teachers[0]->fullname);
    }

    /**
     * A course with nobody teaching it says so, rather than inventing a name.
     */
    public function test_a_course_with_no_teacher_names_nobody(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $this->assertSame([], $detail->teachers);
    }

    /**
     * The groups are built from the activities the course actually holds.
     *
     * A tab that opens onto nothing is worse than a tab that is not there, so
     * a group with no activity in it never comes back.
     */
    public function test_activity_groups_hold_only_what_the_course_contains(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => 'Quiz 1']);

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $keys = array_column($detail->groups, 'key');
        $this->assertContains('quizzes', $keys);
        $this->assertNotContains('assignments', $keys, 'the course has no assignment');
        $this->assertNotContains('workshops', $keys, 'the course has no workshop');

        $quizzes = $detail->groups[array_search('quizzes', $keys, true)];
        $this->assertSame(1, $quizzes->count);
        $this->assertSame('Quiz 1', $quizzes->items[0]->name);
    }

    /**
     * Progress is sessions attended out of sessions scheduled.
     *
     * Attendance is read from counted_present, the same flag that drives XP
     * (AC-14), so the bar and the points cannot disagree.
     */
    public function test_progress_counts_the_sessions_actually_attended(): void {
        global $DB;
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);

        $first = $this->session_on($course->id, time() - (3 * DAYSECS), constants::SESSION_ENDED);
        $this->session_on($course->id, time() - (2 * DAYSECS), constants::SESSION_ENDED);
        $this->session_on($course->id, time() + DAYSECS, constants::SESSION_SCHEDULED);
        $this->session_on($course->id, time() + DAYSECS, constants::SESSION_CANCELLED);

        $DB->insert_record('local_pshub_attendance', (object) [
            'sessionid' => $first,
            'userid' => $learner->id,
            'participant_role' => 'viewer',
            'joined_at' => time() - (3 * DAYSECS),
            'left_at' => time() - (3 * DAYSECS) + 3000,
            'duration_seconds' => 3000,
            'counted_present' => 1,
            'xp_awarded' => 1,
        ]);

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $this->assertCount(3, $detail->sessions, 'a cancelled class is not part of the course');
        $this->assertSame(1, $detail->progress->attended);
        $this->assertSame(3, $detail->progress->total);
        $this->assertSame(33, $detail->progress->percent);
    }

    /**
     * No session scheduled means no progress bar, not a bar at zero.
     *
     * Zero out of zero is not a beginning, and an empty bar reads as failure.
     */
    public function test_a_course_with_no_session_has_no_progress(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $this->assertSame([], $detail->sessions);
        $this->assertFalse($detail->progress);
        $this->assertNull($detail->livesession);
    }

    /**
     * A live session is offered only when there is a room to enter.
     *
     * A join button that opens nothing is worse than no button at all.
     */
    public function test_a_live_session_without_a_room_is_not_offered(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $this->session_on($course->id, time() - 600, constants::SESSION_LIVE);

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $this->assertTrue($detail->sessions[0]->islive);
        $this->assertNull($detail->sessions[0]->joinurl);
        $this->assertNull($detail->livesession);
    }

    /**
     * With a room, the live session is the one the page offers to join.
     */
    public function test_a_live_session_with_a_room_is_the_one_to_join(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        $this->session_on($course->id, time() + DAYSECS, constants::SESSION_SCHEDULED, 77);
        $live = $this->session_on($course->id, time() - 600, constants::SESSION_LIVE, 42);

        $detail = (new learner_service())->get_course_detail($learner->id, $course->id);

        $this->assertNotNull($detail->livesession);
        $this->assertSame($live, (int) $detail->livesession->id);
        $this->assertStringContainsString('id=42', $detail->livesession->joinurl);
    }
}
