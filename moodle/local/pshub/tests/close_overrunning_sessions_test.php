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
use local_pshub\task\close_overrunning_sessions;

/**
 * The third layer of the fifty-minute cap.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\task\close_overrunning_sessions
 */
final class close_overrunning_sessions_test extends \advanced_testcase {

    /**
     * A session row at a chosen time and state.
     *
     * Inserted directly rather than through session_service, because these
     * tests are about sessions that are already in the past - which the
     * service rightly refuses to create.
     *
     * @param int $endsago Seconds since its scheduled end.
     * @param string $status
     * @return int
     */
    private function session(int $endsago, string $status = constants::SESSION_LIVE): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $end = time() - $endsago;

        return (int) $DB->insert_record('local_pshub_session', (object) [
            'courseid' => $course->id,
            'teacherid' => 0,
            'title' => 'Séance',
            'scheduled_start' => $end - (50 * MINSECS),
            'scheduled_end' => $end,
            'duration_minutes' => 50,
            'status' => $status,
            'actual_start' => 0,
            'actual_end' => 0,
            'recording_enabled' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Run the task with its progress output captured.
     *
     * A scheduled task is expected to narrate itself with mtrace(); a test
     * that lets that reach the console is marked risky, and rightly - unread
     * output in a test run hides the output that matters.
     *
     * @return string What it printed.
     */
    private function run_task(): string {
        ob_start();
        (new close_overrunning_sessions())->execute();

        return (string) ob_get_clean();
    }

    /**
     * A class whose end has passed stops calling itself live.
     *
     * This is what the task is for even with no BigBlueButton server: a
     * learner opening the lobby tomorrow must not be told a finished class is
     * still going.
     */
    public function test_a_session_past_its_end_is_closed(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->session(10 * MINSECS);

        $output = $this->run_task();
        $this->assertStringContainsString('closed at its cap', $output);

        $session = $DB->get_record('local_pshub_session', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(constants::SESSION_ENDED, $session->status);
        $this->assertSame((int) $session->scheduled_end, (int) $session->actual_end);
    }

    /**
     * A class that ran four seconds long is not an overrun.
     *
     * The grace exists so a cron firing at the wrong moment does not report
     * every class on the platform as having overrun.
     */
    public function test_a_few_seconds_over_is_left_alone(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->session(5);

        $this->run_task();

        $this->assertSame(constants::SESSION_LIVE,
            $DB->get_field('local_pshub_session', 'status', ['id' => $id]));
    }

    /**
     * A class still to come is untouched.
     */
    public function test_a_future_session_is_untouched(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->session(-DAYSECS, constants::SESSION_SCHEDULED);

        $this->run_task();

        $this->assertSame(constants::SESSION_SCHEDULED,
            $DB->get_field('local_pshub_session', 'status', ['id' => $id]));
    }

    /**
     * A cancelled class is not resurrected as "ended".
     */
    public function test_a_cancelled_session_stays_cancelled(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->session(HOURSECS, constants::SESSION_CANCELLED);

        $this->run_task();

        $this->assertSame(constants::SESSION_CANCELLED,
            $DB->get_field('local_pshub_session', 'status', ['id' => $id]));
    }

    /**
     * Running twice changes nothing the second time.
     *
     * Idempotent by construction: the task only selects sessions that are not
     * yet ended, so a second pass finds none - and the trail does not grow a
     * second row claiming the same class ended twice.
     */
    public function test_running_twice_is_harmless(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->session(10 * MINSECS);
        $this->run_task();
        $this->run_task();

        $this->assertSame(1, $DB->count_records('local_pshub_audit',
            ['target_type' => 'session', 'target_id' => $id]));
    }

    /**
     * The system is named as the actor, not a person.
     *
     * A trail that said a teacher ended a class they were not in would be
     * worse than no trail.
     */
    public function test_the_system_is_the_actor(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $this->session(10 * MINSECS);
        $this->run_task();

        $row = $DB->get_record('local_pshub_audit',
            ['target_type' => 'session', 'target_id' => $id], '*', MUST_EXIST);

        $this->assertSame(0, (int) $row->actorid);
        $this->assertSame('session_ended', $row->action);
    }
}
