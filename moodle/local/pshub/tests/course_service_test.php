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

use local_pshub\course\course_service;
use local_pshub\local\constants;

/**
 * Creating and editing a hub course.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\course\course_service
 */
final class course_service_test extends \advanced_testcase {

    /**
     * What the form hands the service.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function form_data(array $overrides = []): \stdClass {
        return (object) array_merge([
            'fullname' => 'Allemand B1 — conversation professionnelle',
            'summary' => '<p>Vingt séances en direct.</p>',
            'lang_code' => 'de',
            'cefr_level' => 'B1',
            'audience' => 'adult',
            'session_duration_minutes' => 50,
            'is_one_to_one' => 0,
            'max_participants' => 10,
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 22000,
        ], $overrides);
    }

    /**
     * Creating makes both halves at once, and the course starts hidden.
     */
    public function test_it_creates_a_hidden_draft_with_its_catalogue_row(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $courseid = (new course_service())->create($this->form_data());

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $hub = $DB->get_record('local_pshub_course', ['courseid' => $courseid], '*', MUST_EXIST);

        $this->assertSame(0, (int) $course->visible, 'a draft is not visible');
        $this->assertSame(constants::REVIEW_DRAFT, $hub->review_status);
        $this->assertSame('de', $hub->lang_code);
        $this->assertSame('B1', $hub->cefr_level);
        $this->assertSame(22000, (int) $hub->price_amount);
        $this->assertSame(10, (int) $hub->max_participants);
    }

    /**
     * The person who creates it teaches it, through Moodle's own enrolment.
     *
     * Otherwise their own console would not list the course they just made.
     */
    public function test_the_creator_teaches_the_course(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $courseid = (new course_service())->create($this->form_data());

        $context = \context_course::instance($courseid);
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context, $teacher->id));
    }

    /**
     * Two courses with the same title still get distinct shortnames.
     *
     * Moodle requires uniqueness, and a teacher should never meet "shortname
     * already taken" for an identifier they were never asked for.
     */
    public function test_two_courses_with_one_title_do_not_collide(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $service = new course_service();
        $first = $service->create($this->form_data());
        $second = $service->create($this->form_data());

        $this->assertNotEquals(
            $DB->get_field('course', 'shortname', ['id' => $first]),
            $DB->get_field('course', 'shortname', ['id' => $second])
        );
    }

    /**
     * A one-to-one course carries no group size.
     *
     * A stale number would print "10 learners per group maximum" on a course
     * for one person.
     */
    public function test_one_to_one_clears_the_group_size(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $courseid = (new course_service())->create(
            $this->form_data(['is_one_to_one' => 1, 'max_participants' => 10]));

        $this->assertNull($DB->get_field('local_pshub_course', 'max_participants',
            ['courseid' => $courseid]));
    }

    /**
     * A free course is stored at zero whatever the price field said.
     */
    public function test_a_free_course_costs_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $courseid = (new course_service())->create($this->form_data([
            'access_type' => constants::ACCESS_TYPE_FREE,
            'price_amount' => 22000,
        ]));

        $this->assertSame(0, (int) $DB->get_field('local_pshub_course', 'price_amount',
            ['courseid' => $courseid]));
    }

    /**
     * Editing changes both halves and leaves a trail.
     */
    public function test_editing_updates_the_course_and_its_row(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $service = new course_service();
        $courseid = $service->create($this->form_data());

        $service->update($courseid, $this->form_data([
            'fullname' => 'Allemand B2',
            'cefr_level' => 'B2',
            'price_amount' => 30000,
        ]));

        $this->assertSame('Allemand B2', $DB->get_field('course', 'fullname', ['id' => $courseid]));
        $this->assertSame('B2', $DB->get_field('local_pshub_course', 'cefr_level',
            ['courseid' => $courseid]));
        $this->assertSame(2, $DB->count_records('local_pshub_audit',
            ['target_type' => 'course', 'target_id' => $courseid]),
            'creating and editing are both on the trail');
    }

    /**
     * A course under review is frozen to its teacher.
     *
     * A reviewer reading a course should not have it change underneath them.
     */
    public function test_a_teacher_cannot_edit_a_course_under_review(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $service = new course_service();
        $courseid = $service->create($this->form_data());
        $DB->set_field('local_pshub_course', 'review_status', constants::REVIEW_PENDING,
            ['courseid' => $courseid]);

        $this->expectException(\moodle_exception::class);
        $service->update($courseid, $this->form_data(['fullname' => 'Changé en douce']));
    }

    /**
     * A rejected course is editable: that is the whole point of rejecting it
     * with a reason.
     */
    public function test_a_rejected_course_can_be_fixed(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $service = new course_service();
        $courseid = $service->create($this->form_data());
        $DB->set_field('local_pshub_course', 'review_status', constants::REVIEW_REJECTED,
            ['courseid' => $courseid]);

        $service->update($courseid, $this->form_data(['cefr_level' => 'C1']));

        $this->assertSame('C1', $DB->get_field('local_pshub_course', 'cefr_level',
            ['courseid' => $courseid]));
    }

    /**
     * Whoever may publish may edit at any time.
     */
    public function test_a_reviewer_may_edit_a_published_course(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        $service = new course_service();
        $courseid = $service->create($this->form_data());
        $DB->set_field('local_pshub_course', 'review_status', constants::REVIEW_PUBLISHED,
            ['courseid' => $courseid]);

        $admin = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'pshub_admin']);
        role_assign($roleid, $admin->id, \context_system::instance()->id);
        $this->setUser($admin);

        $service->update($courseid, $this->form_data(['price_amount' => 18000]));

        $this->assertSame(18000, (int) $DB->get_field('local_pshub_course', 'price_amount',
            ['courseid' => $courseid]));
    }
}
