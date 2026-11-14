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

use local_pshub\local\constants;
use local_pshub\session\join_verdict;
use local_pshub\session\session_service;

/**
 * Scheduling sessions, and who may walk into one.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\session\session_service
 * @covers     \local_pshub\session\join_verdict
 */
final class session_service_test extends \advanced_testcase {

    /**
     * A published, free hub course with a Moodle course behind it.
     *
     * Free so that the access check passes for anyone enrolled, which keeps
     * these tests about sessions rather than about payment.
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
     * A learner enrolled in a course.
     *
     * @param \stdClass $course
     * @return \stdClass
     */
    private function learner(\stdClass $course): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        return $user;
    }

    /**
     * Somebody counted in the room.
     *
     * @param int $sessionid
     * @param int $userid
     * @return void
     */
    private function in_the_room(int $sessionid, int $userid): void {
        global $DB;

        $DB->insert_record('local_pshub_attendance', (object) [
            'sessionid' => $sessionid,
            'userid' => $userid,
            'participant_role' => 'viewer',
            'joined_at' => time(),
            'left_at' => 0,
            'duration_seconds' => 0,
            'counted_present' => 1,
            'xp_awarded' => 0,
        ]);
    }

    /**
     * A session longer than the cap is brought down to it.
     *
     * The first of the three layers that make AC-4 true: no row can exist
     * claiming ninety minutes.
     */
    public function test_the_cap_bounds_a_session_at_write_time(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('sessioncap', 50 * MINSECS, 'local_pshub');
        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();

        $id = (new session_service())->schedule($course->id, $teacher->id, 'Séance',
            time() + DAYSECS, 90);

        $session = $DB->get_record('local_pshub_session', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(50, (int) $session->duration_minutes);
        $this->assertSame(50 * MINSECS, (int) $session->scheduled_end - (int) $session->scheduled_start);
    }

    /**
     * Ten minutes is the floor, and it is refused rather than rounded.
     */
    public function test_a_session_cannot_be_absurdly_short(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new session_service())->schedule($course->id, $teacher->id, 'Séance', time() + DAYSECS, 5);
    }

    /**
     * Moving a session saves the new time, length and title.
     *
     * The title is here on purpose: the form offers it, and a change the form
     * accepted and the service dropped is the worst kind of bug - nothing
     * fails and the teacher believes it is saved.
     */
    public function test_moving_a_session_saves_all_three(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('sessioncap', 50 * MINSECS, 'local_pshub');
        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();

        $id = $service->schedule($course->id, $teacher->id, 'Avant', time() + DAYSECS, 50);
        $newstart = time() + (3 * DAYSECS);
        $service->reschedule($id, $newstart, 30, 'Après');

        $session = $DB->get_record('local_pshub_session', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Après', $session->title);
        $this->assertSame($newstart, (int) $session->scheduled_start);
        $this->assertSame(30, (int) $session->duration_minutes);
    }

    /**
     * A class that has happened is history, not a plan.
     */
    public function test_an_ended_session_cannot_be_moved(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();
        $id = $service->schedule($course->id, $teacher->id, 'Séance', time() + DAYSECS, 50);
        $DB->set_field('local_pshub_session', 'status', constants::SESSION_ENDED, ['id' => $id]);

        $this->expectException(\moodle_exception::class);
        $service->reschedule($id, time() + (2 * DAYSECS), 50);
    }

    /**
     * Cancelling marks the row and can be repeated without complaint.
     */
    public function test_cancelling_is_recorded_and_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();
        $id = $service->schedule($course->id, $teacher->id, 'Séance', time() + DAYSECS, 50);

        $service->cancel($id);
        $service->cancel($id);

        $this->assertSame(constants::SESSION_CANCELLED,
            $DB->get_field('local_pshub_session', 'status', ['id' => $id]));
        $this->assertSame(2, $DB->count_records('local_pshub_audit',
            ['target_type' => 'session', 'target_id' => $id]),
            'scheduling and the first cancellation; the second changed nothing');
    }

    /**
     * The door opens fifteen minutes before the start, not earlier.
     */
    public function test_the_door_opens_fifteen_minutes_early(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $learner = $this->learner($course);
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();

        $start = time() + HOURSECS;
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        $early = $service->can_join($learner->id, $id, $start - (16 * MINSECS));
        $this->assertFalse($early->is_granted());
        $this->assertSame(join_verdict::REASON_TOO_EARLY, $early->get_reason());
        $this->assertSame($start - (15 * MINSECS), $early->opens_at());

        $ontime = $service->can_join($learner->id, $id, $start - (14 * MINSECS));
        $this->assertTrue($ontime->is_granted());
    }

    /**
     * The door closes when the session ends.
     */
    public function test_the_door_closes_at_the_end(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $learner = $this->learner($course);
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();

        $start = time() + HOURSECS;
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        $verdict = $service->can_join($learner->id, $id, $start + (51 * MINSECS));
        $this->assertFalse($verdict->is_granted());
        $this->assertSame(join_verdict::REASON_ENDED, $verdict->get_reason());
    }

    /**
     * A cancelled class says so, rather than "too early" or "ended".
     *
     * The reason is the whole point: three refusals need three sentences.
     */
    public function test_a_cancelled_session_says_it_was_cancelled(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $learner = $this->learner($course);
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();

        $start = time() + HOURSECS;
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);
        $service->cancel($id);

        $verdict = $service->can_join($learner->id, $id, $start);
        $this->assertSame(join_verdict::REASON_CANCELLED, $verdict->get_reason());
    }

    /**
     * A full group turns away somebody new.
     */
    public function test_a_full_group_is_full(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(['max_participants' => 2]);
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();
        $start = time() + HOURSECS;
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        foreach (range(1, 2) as $unused) {
            $this->in_the_room($id, $this->learner($course)->id);
        }

        $latecomer = $this->learner($course);
        $verdict = $service->can_join($latecomer->id, $id, $start);

        $this->assertFalse($verdict->is_granted());
        $this->assertSame(join_verdict::REASON_FULL, $verdict->get_reason());
    }

    /**
     * Somebody already in the room gets back in, full or not.
     *
     * On these connections a dropped call is ordinary. Counting a returning
     * learner as a new arrival would lock them out of a class they paid for,
     * at the exact moment the network failed them.
     */
    public function test_a_dropped_learner_can_come_back_into_a_full_room(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(['max_participants' => 1]);
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();
        $start = time() + HOURSECS;
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        $learner = $this->learner($course);
        $this->in_the_room($id, $learner->id);

        $this->assertTrue($service->can_join($learner->id, $id, $start)->is_granted());
    }

    /**
     * Access is re-checked at the door (AC-10).
     *
     * Somebody who is not enrolled cannot walk in, whatever the clock says.
     */
    public function test_access_is_rechecked_at_the_door(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $service = new session_service();

        $start = time() + HOURSECS;
        $id = $service->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        $verdict = $service->can_join($outsider->id, $id, $start);
        $this->assertFalse($verdict->is_granted());
        $this->assertSame(join_verdict::REASON_NO_ACCESS, $verdict->get_reason());
    }

    /**
     * A session that does not exist is refused without a fuss.
     */
    public function test_an_unknown_session_is_refused(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $verdict = (new session_service())->can_join($user->id, -1);

        $this->assertFalse($verdict->is_granted());
        $this->assertSame(join_verdict::REASON_UNKNOWN, $verdict->get_reason());
        $this->assertNull($verdict->get_session());
    }

    /**
     * Scheduling puts the class in the course calendar.
     *
     * One `course` event, not one per person: it is visible to everybody
     * enrolled, which is the teacher and the learners at once, and a learner
     * enrolled tomorrow sees it without anybody writing a second row.
     */
    public function test_scheduling_writes_one_calendar_event(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $start = time() + DAYSECS;

        $id = (new session_service())->schedule($course->id, $teacher->id, 'Séance', $start, 50);

        $events = $DB->get_records('event', ['component' => 'local_pshub', 'instance' => $id]);
        $this->assertCount(1, $events);

        $event = reset($events);
        $this->assertSame('course', $event->eventtype);
        $this->assertSame((int) $course->id, (int) $event->courseid);
        $this->assertSame($start, (int) $event->timestart);
        $this->assertSame(50 * MINSECS, (int) $event->timeduration);
    }

    /**
     * Moving the session moves the diary entry, and does not leave the old one.
     */
    public function test_moving_a_session_moves_its_calendar_event(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();
        $id = $service->schedule($course->id, $teacher->id, 'Avant', time() + DAYSECS, 50);

        $newstart = time() + (4 * DAYSECS);
        $service->reschedule($id, $newstart, 30, 'Après');

        $events = $DB->get_records('event', ['component' => 'local_pshub', 'instance' => $id]);
        $this->assertCount(1, $events, 'moved, not duplicated');

        $event = reset($events);
        $this->assertSame($newstart, (int) $event->timestart);
        $this->assertSame(30 * MINSECS, (int) $event->timeduration);
        $this->assertSame('Après', $event->name);
    }

    /**
     * A cancelled class leaves the calendar.
     *
     * An appointment that is not happening should stop appearing in the month
     * view; the cancellation itself stays on the session row and the trail.
     */
    public function test_cancelling_removes_the_calendar_event(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $service = new session_service();
        $id = $service->schedule($course->id, $teacher->id, 'Séance', time() + DAYSECS, 50);

        $service->cancel($id);

        $this->assertSame(0, $DB->count_records('event',
            ['component' => 'local_pshub', 'instance' => $id]));
    }
}
