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

use local_pshub\catalogue\price_resolver;
use local_pshub\form\course_type_form;
use local_pshub\local\constants;

/**
 * The course-type admin form, and AC-8 end to end.
 *
 * AC-8 says an administrator creates a type, attaches it to a course, and its
 * badge and pricing take effect with no deployment. The price resolver tests
 * prove the second half against rows inserted directly; these prove the form's
 * validation is what stops a bad row reaching them.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\form\course_type_form
 */
final class course_type_form_test extends \advanced_testcase {

    /**
     * A complete, valid submission.
     *
     * @param array $overrides
     * @return array
     */
    private function submission(array $overrides = []): array {
        return array_merge([
            'id' => 0,
            'code' => 'rentree',
            'name_fr' => 'Rentrée -35 %',
            'name_en' => 'Back to school -35%',
            'kind' => 'discount',
            'discount_percent' => 35,
            'grants_free_access' => 0,
            'badge_style' => 'hot',
            'sortorder' => 5,
            'is_active' => 1,
        ], $overrides);
    }

    /**
     * Run the form's server-side validation over a submission.
     *
     * @param array $data
     * @return array Errors, keyed by field.
     */
    private function validate(array $data): array {
        return (new course_type_form())->validation($data, []);
    }

    /**
     * A well-formed type passes.
     */
    public function test_valid_submission_has_no_errors(): void {
        $this->resetAfterTest();
        $this->assertSame([], $this->validate($this->submission()));
    }

    /**
     * The code is the platform's handle on a type, and the database has a
     * unique index on it. Catching the clash here turns a stack trace into a
     * message beside the field.
     */
    public function test_duplicate_code_is_refused(): void {
        $this->resetAfterTest();

        // 'promo20' is seeded by db/install.php.
        $errors = $this->validate($this->submission(['code' => 'promo20']));

        $this->assertArrayHasKey('code', $errors);
    }

    /**
     * Editing a type keeps its own code. The uniqueness check must exclude the
     * row being edited, or saving without renaming would fail.
     */
    public function test_editing_a_type_may_keep_its_own_code(): void {
        global $DB;
        $this->resetAfterTest();

        $id = $DB->get_field('local_pshub_course_type', 'id', ['code' => 'promo20']);
        $errors = $this->validate($this->submission(['id' => $id, 'code' => 'promo20']));

        $this->assertArrayNotHasKey('code', $errors);
    }

    /**
     * A discount outside 1-100 is a typo. The resolver would floor it at zero
     * silently, so the administrator hears about it instead.
     */
    public function test_discount_must_be_in_range(): void {
        $this->resetAfterTest();

        $this->assertArrayHasKey('discount_percent',
            $this->validate($this->submission(['discount_percent' => 0])));
        $this->assertArrayHasKey('discount_percent',
            $this->validate($this->submission(['discount_percent' => 150])));
        $this->assertArrayNotHasKey('discount_percent',
            $this->validate($this->submission(['discount_percent' => 100])));
    }

    /**
     * A discount percentage on a non-discount type is ignored rather than
     * rejected, so switching kind mid-edit is not a dead end.
     */
    public function test_discount_is_ignored_for_other_kinds(): void {
        $this->resetAfterTest();

        $errors = $this->validate($this->submission([
            'kind' => 'highlight', 'discount_percent' => 999,
        ]));

        $this->assertArrayNotHasKey('discount_percent', $errors);
    }

    /**
     * Free and discount together is a contradiction the resolver settles
     * silently in favour of free. Refusing it stops an administrator believing
     * a discount is being applied when it is not.
     */
    public function test_free_and_discount_together_is_refused(): void {
        $this->resetAfterTest();

        $errors = $this->validate($this->submission([
            'grants_free_access' => 1, 'kind' => 'discount',
        ]));

        $this->assertArrayHasKey('grants_free_access', $errors);
    }

    /**
     * AC-8, end to end. A type an administrator invents, attached to a course,
     * changes that course's price and badge with no code change.
     */
    public function test_invented_type_prices_a_course_with_no_deployment(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) [
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 2000000,
            'price_currency' => 'XAF',
            'review_status' => constants::REVIEW_PUBLISHED,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        // Exactly what coursetype_edit.php writes for a valid submission.
        $data = $this->submission();
        $this->assertSame([], $this->validate($data));

        $typeid = $DB->insert_record('local_pshub_course_type', (object) [
            'code' => $data['code'],
            'name_fr' => $data['name_fr'],
            'name_en' => $data['name_en'],
            'kind' => $data['kind'],
            'discount_percent' => $data['discount_percent'],
            'grants_free_access' => $data['grants_free_access'],
            'badge_style' => $data['badge_style'],
            'sortorder' => $data['sortorder'],
            'is_active' => $data['is_active'],
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_pshub_course_type_map', (object) [
            'courseid' => $course->id, 'typeid' => $typeid,
        ]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertEquals(2000000, $price->base);
        $this->assertEquals(1300000, $price->amount);
        $this->assertEquals(35, $price->discountpercent);
        $this->assertEquals('Back to school -35%', $price->badges[0]->label);
        $this->assertEquals('hot', $price->badges[0]->style);
    }
}
