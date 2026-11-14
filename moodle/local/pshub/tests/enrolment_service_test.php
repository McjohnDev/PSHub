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

use local_pshub\access\access_service;
use local_pshub\enrolment\enrolment_service;
use local_pshub\local\constants;

/**
 * Free enrolment, and the atomicity AC-9 depends on.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\enrolment\enrolment_service
 */
final class enrolment_service_test extends \advanced_testcase {

    /**
     * @param array $overrides
     * @return \stdClass
     */
    private function hub_course(array $overrides = []): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) array_merge([
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'access_type' => constants::ACCESS_TYPE_FREE,
            'price_amount' => 0,
            'price_currency' => 'XAF',
            'review_status' => constants::REVIEW_PUBLISHED,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides));

        return $course;
    }

    /**
     * AC-9. Enrolling in a free course creates the access right AND the Moodle
     * enrolment. Either alone is a support ticket.
     */
    public function test_free_enrolment_creates_right_and_enrolment(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $service = new enrolment_service();
        $verdict = $service->enrol_free($user->id, $course->id);

        $this->assertTrue($verdict->is_granted());

        $right = (new access_service())->find($user->id, 'course', $course->id);
        $this->assertNotFalse($right, 'an access right must exist');
        $this->assertEquals(constants::SOURCE_AUTO, $right->source);
        $this->assertEquals(constants::ACCESS_ACTIVE, $right->status);

        $this->assertTrue(
            $service->is_enrolled($user->id, $course->id),
            'the learner must actually be enrolled in Moodle'
        );
    }

    /**
     * A paid course is refused, and refusing must write nothing at all. A
     * half-completed enrolment on a course the learner has not paid for is
     * worse than a clean denial.
     */
    public function test_paid_course_writes_nothing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course([
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 1500000,
        ]);

        $service = new enrolment_service();
        $verdict = $service->enrol_free($user->id, $course->id);

        $this->assertFalse($verdict->is_granted());
        $this->assertFalse((new access_service())->find($user->id, 'course', $course->id));
        $this->assertFalse($service->is_enrolled($user->id, $course->id));
    }

    /**
     * Enrolling twice is harmless. A learner double-tapping "start" on a slow
     * connection must not produce two rights or an error.
     */
    public function test_enrolling_twice_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $service = new enrolment_service();
        $service->enrol_free($user->id, $course->id);
        $service->enrol_free($user->id, $course->id);

        $this->assertEquals(1, $DB->count_records('local_pshub_access', [
            'userid' => $user->id, 'target_type' => 'course', 'target_id' => $course->id,
        ]));
        $this->assertTrue($service->is_enrolled($user->id, $course->id));
    }

    /**
     * A validated payment opens access with source=payment and enrols, the
     * same way and through the same code path.
     */
    public function test_paid_grant_enrols_and_records_source(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course([
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 1500000,
        ]);

        $service = new enrolment_service();
        $service->grant_paid_access($user->id, $course->id);

        $right = (new access_service())->find($user->id, 'course', $course->id);
        $this->assertNotFalse($right);
        $this->assertEquals(constants::SOURCE_PAYMENT, $right->source);
        $this->assertTrue($service->is_enrolled($user->id, $course->id));
    }

    /**
     * Once access is granted by payment, the gate opens for that course.
     */
    public function test_gate_opens_after_paid_grant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course([
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 1500000,
        ]);

        $gating = new \local_pshub\access\gating_service();
        $this->assertFalse($gating->check($user->id, $course->id)->is_granted());

        (new enrolment_service())->grant_paid_access($user->id, $course->id);

        $this->assertTrue($gating->check($user->id, $course->id)->is_granted());
    }
}
