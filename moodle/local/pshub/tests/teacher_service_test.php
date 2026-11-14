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
use local_pshub\teacher\teacher_service;

/**
 * What a teacher's console shows them.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\teacher\teacher_service
 */
final class teacher_service_test extends \advanced_testcase {

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
            'lang_code' => 'en',
            'cefr_level' => 'B1',
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
     * Only the courses this person actually teaches.
     */
    public function test_it_lists_the_courses_they_teach(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $mine = $this->hub_course();
        $theirs = $this->hub_course();

        $this->getDataGenerator()->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $theirs->id, 'student');

        $courses = (new teacher_service())->get_courses($teacher->id);

        $this->assertCount(1, $courses, 'being enrolled is not teaching');
        $this->assertSame((int) $mine->id, $courses[0]->courseid);
    }

    /**
     * Drafts and rejections are shown: this is the teacher's own workbench.
     *
     * A rejected course they cannot see is a decision nobody told them about.
     */
    public function test_drafts_and_rejections_are_shown_with_their_reason(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $draft = $this->hub_course(constants::REVIEW_DRAFT);
        $rejected = $this->hub_course(constants::REVIEW_REJECTED,
            ['review_comment' => 'Le niveau annoncé ne correspond pas au contenu.']);

        $this->getDataGenerator()->enrol_user($teacher->id, $draft->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $rejected->id, 'editingteacher');

        $courses = (new teacher_service())->get_courses($teacher->id);
        $bystatus = array_column($courses, null, 'status');

        $this->assertArrayHasKey(constants::REVIEW_DRAFT, $bystatus);
        $this->assertArrayHasKey(constants::REVIEW_REJECTED, $bystatus);
        $this->assertTrue($bystatus[constants::REVIEW_REJECTED]->hascomment);
        $this->assertStringContainsString('niveau annoncé',
            $bystatus[constants::REVIEW_REJECTED]->reviewcomment);
        $this->assertFalse($bystatus[constants::REVIEW_DRAFT]->hascomment);
    }

    /**
     * Learners are the people enrolled who are not staff there.
     */
    public function test_it_counts_learners_and_not_colleagues(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $colleague = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($colleague->id, $course->id, 'editingteacher');
        foreach (range(1, 3) as $unused) {
            $learner = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        }

        $courses = (new teacher_service())->get_courses($teacher->id);

        $this->assertSame(3, $courses[0]->learners);
        $this->assertStringContainsString('3', $courses[0]->learnerlabel);
    }

    /**
     * The three figures at the top are counted from the same list.
     */
    public function test_totals_come_from_the_same_courses_the_page_lists(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $published = $this->hub_course();
        $pending = $this->hub_course(constants::REVIEW_PENDING);
        $draft = $this->hub_course(constants::REVIEW_DRAFT);

        foreach ([$published, $pending, $draft] as $course) {
            $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        }
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $published->id);

        $service = new teacher_service();
        $totals = $service->get_totals($service->get_courses($teacher->id));

        $this->assertSame(1, $totals->published);
        $this->assertSame(1, $totals->pending);
        $this->assertSame(1, $totals->learners, 'a draft with nobody in it adds nothing');
    }

    /**
     * Sessions: theirs, still to come, never a cancelled class.
     */
    public function test_upcoming_sessions_are_theirs_and_in_order(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $session = function(int $teacherid, int $start, string $status) use ($course, $DB): int {
            return (int) $DB->insert_record('local_pshub_session', (object) [
                'courseid' => $course->id,
                'teacherid' => $teacherid,
                'title' => 'Séance',
                'scheduled_start' => $start,
                'scheduled_end' => $start + 3000,
                'duration_minutes' => 50,
                'status' => $status,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        };

        $soon = $session($teacher->id, time() + DAYSECS, constants::SESSION_SCHEDULED);
        $later = $session($teacher->id, time() + (2 * DAYSECS), constants::SESSION_SCHEDULED);
        $session($teacher->id, time() - (2 * DAYSECS), constants::SESSION_SCHEDULED);
        $session($teacher->id, time() + DAYSECS, constants::SESSION_CANCELLED);
        $session($other->id, time() + DAYSECS, constants::SESSION_SCHEDULED);

        $sessions = (new teacher_service())->get_upcoming_sessions($teacher->id);

        $this->assertCount(2, $sessions);
        $this->assertEquals($soon, $sessions[0]->id, 'the nearest session comes first');
        $this->assertEquals($later, $sessions[1]->id);
    }

    /**
     * The administration's brief is read from the table that holds it.
     */
    public function test_it_returns_the_briefs_written_for_them(): void {
        global $DB;
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $admin = $this->getDataGenerator()->create_user();

        foreach ([$teacher->id, $other->id] as $who) {
            $DB->insert_record('local_pshub_teacher_assign', (object) [
                'teacherid' => $who,
                'brief' => 'Couvrir A2.1 à A2.4.',
                'assignedby' => $admin->id,
                'assigned_at' => time(),
            ]);
        }

        $briefs = (new teacher_service())->get_briefs($teacher->id);

        $this->assertCount(1, $briefs);
        $this->assertStringContainsString('A2.1', $briefs[0]->brief);
    }

    /**
     * A teacher with nothing yet gets empty answers, not errors.
     */
    public function test_a_teacher_with_nothing_yet_is_fine(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $service = new teacher_service();

        $this->assertSame([], $service->get_courses($teacher->id));
        $this->assertSame([], $service->get_upcoming_sessions($teacher->id));
        $this->assertSame([], $service->get_briefs($teacher->id));

        $totals = $service->get_totals([]);
        $this->assertSame(0, $totals->published);
        $this->assertSame(0, $totals->learners);
    }
}
