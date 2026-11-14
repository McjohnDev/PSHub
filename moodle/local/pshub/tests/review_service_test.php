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
use local_pshub\review\review_service;

/**
 * The course validation workflow.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\review\review_service
 */
final class review_service_test extends \advanced_testcase {

    /**
     * A hub course ready to be submitted, with a Moodle course behind it.
     *
     * @param string $status
     * @param array $overrides
     * @return \stdClass The Moodle course.
     */
    private function hub_course(string $status = constants::REVIEW_DRAFT,
            array $overrides = []): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course([
            'summary' => 'Vingt séances en direct.',
            'summaryformat' => FORMAT_HTML,
            'visible' => 0,
        ]);
        $DB->insert_record('local_pshub_course', (object) array_merge([
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'lang_code' => 'de',
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
     * Somebody who may teach this course.
     *
     * @param \stdClass $course
     * @return \stdClass
     */
    private function teacher(\stdClass $course): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        return $user;
    }

    /**
     * Somebody who may publish.
     *
     * @return \stdClass
     */
    private function reviewer(): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'pshub_admin']);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        return $user;
    }

    /**
     * The course's status and Moodle visibility, which must never disagree.
     *
     * @param int $courseid
     * @return array [status, visible]
     */
    private function state(int $courseid): array {
        global $DB;

        return [
            $DB->get_field('local_pshub_course', 'review_status', ['courseid' => $courseid]),
            (int) $DB->get_field('course', 'visible', ['id' => $courseid]),
        ];
    }

    /**
     * A complete course can be submitted; the status moves and the trail grows.
     */
    public function test_a_teacher_can_submit_a_complete_course(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course();
        $this->teacher($course);

        (new review_service())->submit($course->id);

        $this->assertSame([constants::REVIEW_PENDING, 0], $this->state($course->id),
            'a course waiting for review is not visible');
        $this->assertSame(1, $DB->count_records('local_pshub_audit',
            ['target_type' => 'course', 'target_id' => $course->id]));
    }

    /**
     * An incomplete course is refused, and told what is missing.
     */
    public function test_an_incomplete_course_cannot_be_submitted(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_DRAFT, ['cefr_level' => null, 'lang_code' => null]);
        $this->teacher($course);

        $service = new review_service();
        $problems = $service->submission_problems($course->id);

        $this->assertCount(2, $problems, 'the language and the level are both missing');

        $this->expectException(\moodle_exception::class);
        $service->submit($course->id);
    }

    /**
     * A paid course priced at nothing is incomplete.
     */
    public function test_a_paid_course_with_no_price_is_incomplete(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_DRAFT,
            ['access_type' => constants::ACCESS_TYPE_PAID, 'price_amount' => 0]);
        $this->teacher($course);

        $this->assertNotEmpty((new review_service())->submission_problems($course->id));
    }

    /**
     * Approving publishes the course and makes it visible, in one place.
     */
    public function test_approving_publishes_and_shows_the_course(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_PENDING);
        $reviewer = $this->reviewer();

        (new review_service())->approve($course->id);

        $this->assertSame([constants::REVIEW_PUBLISHED, 1], $this->state($course->id));
        $this->assertEquals($reviewer->id,
            $DB->get_field('local_pshub_course', 'reviewedby', ['courseid' => $course->id]));
    }

    /**
     * A rejection without a reason is refused.
     *
     * The rule this class exists for: a teacher told only "no" has been told
     * nothing (§3.4, AC-1).
     */
    public function test_a_rejection_must_carry_its_reason(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_PENDING);
        $this->reviewer();

        $this->expectException(\moodle_exception::class);
        (new review_service())->reject($course->id, '   ');
    }

    /**
     * A reasoned rejection stores the reason and hides the course.
     */
    public function test_a_reasoned_rejection_is_recorded(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_PENDING);
        $this->reviewer();

        (new review_service())->reject($course->id, 'Le niveau annoncé ne correspond pas.');

        $this->assertSame([constants::REVIEW_REJECTED, 0], $this->state($course->id));
        $this->assertStringContainsString('niveau annoncé',
            $DB->get_field('local_pshub_course', 'review_comment', ['courseid' => $course->id]));
    }

    /**
     * A rejected course can be fixed and sent back, and approval clears the
     * old reason: a published course must not carry the note that once
     * rejected it.
     */
    public function test_a_rejected_course_goes_round_again(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_PENDING);
        $this->reviewer();
        $service = new review_service();
        $service->reject($course->id, 'À revoir.');

        $this->teacher($course);
        $service->submit($course->id);
        $this->assertSame(constants::REVIEW_PENDING, $this->state($course->id)[0]);

        $this->reviewer();
        $service->approve($course->id);

        $this->assertSame([constants::REVIEW_PUBLISHED, 1], $this->state($course->id));
        $this->assertNull($DB->get_field('local_pshub_course', 'review_comment',
            ['courseid' => $course->id]));
        $this->assertSame(3, $DB->count_records('local_pshub_audit',
            ['target_type' => 'course', 'target_id' => $course->id]),
            'submit, reject and approve are each on the trail');
    }

    /**
     * Only a pending course can be decided on.
     */
    public function test_a_published_course_is_not_waiting_for_a_decision(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_PUBLISHED);
        $this->reviewer();

        $this->expectException(\moodle_exception::class);
        (new review_service())->approve($course->id);
    }

    /**
     * A teacher cannot publish, including their own course (AC-1).
     *
     * Not because the capability is forbidden to them, but because it was
     * never granted: publishcourse declares only the manager archetype.
     */
    public function test_a_teacher_cannot_approve_their_own_course(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(constants::REVIEW_PENDING);
        $this->teacher($course);

        $this->expectException(\required_capability_exception::class);
        (new review_service())->approve($course->id);
    }
}
