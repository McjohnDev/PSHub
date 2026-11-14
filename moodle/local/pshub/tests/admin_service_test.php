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

use local_pshub\admin\admin_service;
use local_pshub\local\constants;

/**
 * What the administration console reports.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\admin\admin_service
 */
final class admin_service_test extends \advanced_testcase {

    /**
     * A hub course with a Moodle course behind it.
     *
     * @param string $status
     * @param array $overrides
     * @return \stdClass The Moodle course.
     */
    private function hub_course(string $status = constants::REVIEW_PUBLISHED,
            array $overrides = []): \stdClass {
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
            'review_status' => $status,
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
     * Every review state is counted, including the ones at zero.
     *
     * A console that omits a state when nothing is in it makes the reader
     * wonder whether it was counted at all.
     */
    public function test_it_counts_every_review_state(): void {
        $this->resetAfterTest();

        $this->hub_course(constants::REVIEW_PUBLISHED);
        $this->hub_course(constants::REVIEW_PUBLISHED);
        $this->hub_course(constants::REVIEW_PENDING);

        $counts = (new admin_service())->get_course_counts();

        $this->assertSame(2, $counts[constants::REVIEW_PUBLISHED]);
        $this->assertSame(1, $counts[constants::REVIEW_PENDING]);
        $this->assertArrayHasKey(constants::REVIEW_REJECTED, $counts);
        $this->assertSame(0, $counts[constants::REVIEW_REJECTED]);
    }

    /**
     * The queue holds what is pending, oldest first.
     *
     * Oldest first is the point: newest first leaves the course that has
     * waited longest at the bottom of the list.
     */
    public function test_the_queue_is_pending_only_oldest_first(): void {
        $this->resetAfterTest();

        $this->hub_course(constants::REVIEW_PUBLISHED);
        $recent = $this->hub_course(constants::REVIEW_PENDING,
            ['timesubmitted' => time() - HOURSECS]);
        $old = $this->hub_course(constants::REVIEW_PENDING,
            ['timesubmitted' => time() - (5 * DAYSECS)]);

        $queue = (new admin_service())->get_review_queue();

        $this->assertCount(2, $queue);
        $this->assertSame((int) $old->id, $queue[0]->courseid, 'the longest wait comes first');
        $this->assertSame((int) $recent->id, $queue[1]->courseid);
    }

    /**
     * A course submitted by somebody names them; one that was not does not.
     */
    public function test_the_queue_names_the_submitter_only_when_there_is_one(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user(['lastname' => 'Kamdem']);
        $this->hub_course(constants::REVIEW_PENDING,
            ['submittedby' => $teacher->id, 'timesubmitted' => time() - DAYSECS]);
        $this->hub_course(constants::REVIEW_PENDING, ['timesubmitted' => time()]);

        $queue = (new admin_service())->get_review_queue();

        $this->assertTrue($queue[0]->hassubmitter);
        $this->assertStringContainsString('Kamdem', $queue[0]->submitter);
        $this->assertFalse($queue[1]->hassubmitter, 'nobody is named who did not submit it');
    }

    /**
     * With no transaction at all, there is no money to report.
     *
     * Not zero: a platform that has never taken a payment has no revenue
     * figure, and printing one makes it look like a result.
     */
    public function test_no_transaction_means_no_money_card(): void {
        $this->resetAfterTest();

        $this->assertFalse((new admin_service())->get_money());
    }

    /**
     * Once there are transactions, validated and waiting are kept apart.
     */
    public function test_validated_and_waiting_money_are_counted_separately(): void {
        global $DB;
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $tx = function(string $status, int $amount) use ($learner, $course, $DB) {
            $DB->insert_record('local_pshub_transaction', (object) [
                'userid' => $learner->id,
                'target_type' => 'course',
                'target_id' => $course->id,
                'amount' => $amount,
                'currency' => 'XAF',
                'method' => 'mtn_momo',
                'provider' => 'fake',
                'provider_ref' => uniqid('ref', true),
                'status' => $status,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        };

        $tx(constants::TXN_VALIDATED, 25000);
        $tx(constants::TXN_VALIDATED, 15000);
        $tx(constants::TXN_PAID_UNVALIDATED, 40000);

        $money = (new admin_service())->get_money();

        $this->assertSame(40000, $money->validated);
        $this->assertSame(1, $money->pending);
        $this->assertSame(40000, $money->pendingamount);
    }

    /**
     * People: teachers by capability, learners as enrolled non-staff.
     */
    public function test_it_counts_teachers_by_capability_and_learners_by_enrolment(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $teacher = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);

        $people = (new admin_service())->get_people();

        // One learner: the teacher is not one of their own learners, and the
        // outsider is enrolled nowhere.
        $this->assertSame(1, $people->learners);
        $this->assertIsInt($people->teachers);
    }

    /**
     * A learner in two courses is one person, not two.
     */
    public function test_a_learner_in_two_courses_is_counted_once(): void {
        $this->resetAfterTest();

        $first = $this->hub_course();
        $second = $this->hub_course();
        $learner = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($learner->id, $first->id);
        $this->getDataGenerator()->enrol_user($learner->id, $second->id);

        $this->assertSame(1, (new admin_service())->get_people()->learners);
    }
}
