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
use local_pshub\task\send_session_reminders;

/**
 * Reminders before a live class (AC-6).
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\task\send_session_reminders
 */
final class send_session_reminders_test extends \advanced_testcase {

    /** @var \stdClass The course every session in these tests belongs to. */
    private $course;

    /** @var \stdClass */
    private $teacher;

    /**
     * A published hub course with a teacher and two learners.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) [
            'courseid' => $this->course->id,
            'categoryid' => $this->course->category,
            'lang_code' => 'fr',
            'cefr_level' => 'A2',
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
        ]);

        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');

        foreach (range(1, 2) as $unused) {
            $learner = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($learner->id, $this->course->id);
        }
    }

    /**
     * A session starting in a given number of seconds.
     *
     * @param int $startsin
     * @param int|null $createdago How long ago it was scheduled.
     * @return int
     */
    private function session(int $startsin, ?int $createdago = null): int {
        global $DB;

        $start = time() + $startsin;
        $created = time() - ($createdago ?? (2 * DAYSECS));

        return (int) $DB->insert_record('local_pshub_session', (object) [
            'courseid' => $this->course->id,
            'teacherid' => $this->teacher->id,
            'title' => 'Séance',
            'scheduled_start' => $start,
            'scheduled_end' => $start + (50 * MINSECS),
            'duration_minutes' => 50,
            'status' => constants::SESSION_SCHEDULED,
            'actual_start' => 0,
            'actual_end' => 0,
            'recording_enabled' => 0,
            'timecreated' => $created,
            'timemodified' => $created,
        ]);
    }

    /**
     * Run the task with its output captured.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new send_session_reminders())->execute();
        ob_end_clean();
    }

    /**
     * How many messages were sent.
     *
     * @param \phpunit_message_sink $sink
     * @return int
     */
    private function count_reminders(\phpunit_message_sink $sink): int {
        $count = 0;
        foreach ($sink->get_messages() as $message) {
            if ($message->component === 'local_pshub' && $message->eventtype === 'sessionreminder') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * An hour before the class, teacher and both learners are told.
     */
    public function test_everyone_is_reminded(): void {
        global $DB;

        $sink = $this->redirectMessages();
        $id = $this->session(50 * MINSECS);

        $this->run_task();

        // One reminder each, and two rows: one per audience.
        $this->assertSame(3, $this->count_reminders($sink));
        $this->assertSame(2, $DB->count_records('local_pshub_reminder', ['sessionid' => $id]));
    }

    /**
     * Running the task again sends nothing more.
     *
     * The unique index is what makes this true, not a check: the insert is the
     * claim, and a duplicate key is how a second run learns it is too late.
     */
    public function test_a_second_run_sends_nothing(): void {
        global $DB;

        $sink = $this->redirectMessages();
        $id = $this->session(50 * MINSECS);

        $this->run_task();
        $before = $this->count_reminders($sink);
        $this->run_task();

        $this->assertSame($before, $this->count_reminders($sink));
        $this->assertSame(2, $DB->count_records('local_pshub_reminder', ['sessionid' => $id]));
    }

    /**
     * Each offset is its own reminder.
     *
     * Each reminder has its own window, which closes when the next opens: a
     * class three days away gets nothing, one twenty hours away gets the
     * day-before reminder, one five minutes away gets only the last.
     */
    public function test_each_offset_fires_once(): void {
        global $DB;

        $this->redirectMessages();

        $far = $this->session(3 * DAYSECS);
        $day = $this->session(20 * HOURSECS);
        $soon = $this->session(5 * MINSECS);

        $this->run_task();

        $this->assertSame(0, $DB->count_records('local_pshub_reminder', ['sessionid' => $far]),
            'three days away is nobody\'s business yet');
        $this->assertSame(2, $DB->count_records('local_pshub_reminder',
            ['sessionid' => $day, 'offset_minutes' => 1440]));
        $this->assertSame(0, $DB->count_records('local_pshub_reminder',
            ['sessionid' => $day, 'offset_minutes' => 60]));
        // Only the last reminder: each has its own window, so a class five
        // minutes away is past the day-before and hour-before ones rather
        // than owed all three at once.
        $this->assertSame(2, $DB->count_records('local_pshub_reminder', ['sessionid' => $soon]));
        $this->assertSame(2, $DB->count_records('local_pshub_reminder',
            ['sessionid' => $soon, 'offset_minutes' => 10]));
    }

    /**
     * A class scheduled at short notice does not fire yesterday's reminder.
     *
     * Saving a session for this evening should not immediately send "your
     * class is in 24 hours".
     */
    public function test_a_late_booking_skips_the_offsets_it_missed(): void {
        global $DB;

        $this->redirectMessages();

        // Starts in 30 minutes, and was only created a minute ago.
        $id = $this->session(30 * MINSECS, MINSECS);

        $this->run_task();

        $this->assertSame(0, $DB->count_records('local_pshub_reminder',
            ['sessionid' => $id, 'offset_minutes' => 1440]));
        $this->assertSame(0, $DB->count_records('local_pshub_reminder',
            ['sessionid' => $id, 'offset_minutes' => 60]));
    }

    /**
     * A cancelled class reminds nobody.
     */
    public function test_a_cancelled_session_reminds_nobody(): void {
        global $DB;

        $sink = $this->redirectMessages();
        $id = $this->session(50 * MINSECS);
        $DB->set_field('local_pshub_session', 'status', constants::SESSION_CANCELLED, ['id' => $id]);

        $this->run_task();

        $this->assertSame(0, $this->count_reminders($sink));
        $this->assertSame(0, $DB->count_records('local_pshub_reminder', ['sessionid' => $id]));
    }

    /**
     * A class already under way is not told it is about to start.
     */
    public function test_a_started_session_is_not_announced(): void {
        global $DB;

        $sink = $this->redirectMessages();
        $this->session(-5 * MINSECS);

        $this->run_task();

        $this->assertSame(0, $this->count_reminders($sink));
    }

    /**
     * Moving a class owes its reminders again (PSH-S08-04).
     */
    public function test_moving_a_session_requeues_its_reminders(): void {
        global $DB;

        $this->redirectMessages();
        $id = $this->session(50 * MINSECS);
        $this->run_task();
        $this->assertSame(2, $DB->count_records('local_pshub_reminder', ['sessionid' => $id]));

        (new \local_pshub\session\session_service())
            ->reschedule($id, time() + (3 * DAYSECS), 50);

        $this->assertSame(0, $DB->count_records('local_pshub_reminder', ['sessionid' => $id]),
            'a moved class is a new appointment');
    }

    /**
     * The message names the time the class starts, not an interval.
     *
     * Cron can be late. "In one hour", sent ninety minutes late, is worse than
     * no reminder at all.
     */
    public function test_the_message_names_the_time(): void {
        $sink = $this->redirectMessages();
        $id = $this->session(50 * MINSECS);

        $this->run_task();

        $messages = array_values(array_filter($sink->get_messages(),
            fn($m) => $m->eventtype === 'sessionreminder'));
        $this->assertNotEmpty($messages);

        $body = $messages[0]->fullmessage;
        $this->assertStringNotContainsString('1 h', $body);
        $this->assertStringContainsString(
            userdate((int) $this->get_start($id), get_string('strftimedaydatetime', 'langconfig')),
            $body);
    }

    /**
     * @param int $id
     * @return int
     */
    private function get_start(int $id): int {
        global $DB;

        return (int) $DB->get_field('local_pshub_session', 'scheduled_start', ['id' => $id]);
    }
}
