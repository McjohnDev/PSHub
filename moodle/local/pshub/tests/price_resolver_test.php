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
use local_pshub\local\constants;

/**
 * Price resolution, and the configurable course types behind AC-8.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\catalogue\price_resolver
 */
final class price_resolver_test extends \advanced_testcase {

    /**
     * @param int $amount Minor units.
     * @return \stdClass
     */
    private function paid_course(int $amount = 1500000): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) [
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => $amount,
            'price_currency' => 'XAF',
            'review_status' => constants::REVIEW_PUBLISHED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return $course;
    }

    /**
     * Attach a course type, creating it.
     *
     * @param int $courseid
     * @param array $type
     * @return int Type id.
     */
    private function attach_type(int $courseid, array $type): int {
        global $DB;

        $typeid = $DB->insert_record('local_pshub_course_type', (object) array_merge([
            'name_fr' => 'Type', 'name_en' => 'Type', 'kind' => 'custom',
            'discount_percent' => 0, 'grants_free_access' => 0,
            'badge_style' => 'exam', 'sortorder' => 0, 'is_active' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ], $type));
        $DB->insert_record('local_pshub_course_type_map', (object) [
            'courseid' => $courseid, 'typeid' => $typeid,
        ]);

        return (int) $typeid;
    }

    /**
     * No types means the base price stands.
     */
    public function test_base_price_without_types(): void {
        $this->resetAfterTest();
        $price = (new price_resolver())->resolve($this->paid_course(1500000)->id);

        $this->assertFalse($price->isfree);
        $this->assertEquals(1500000, $price->amount);
        $this->assertEquals(0, $price->discountpercent);
    }

    /**
     * A discount type reduces the price and exposes both figures, so the card
     * can strike through the original (AC-8).
     */
    public function test_discount_type_applies(): void {
        $this->resetAfterTest();
        $course = $this->paid_course(1500000);
        $this->attach_type($course->id, [
            'code' => 'promo20x', 'name_fr' => '-20 %', 'kind' => 'discount',
            'discount_percent' => 20, 'badge_style' => 'promo',
        ]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertEquals(1500000, $price->base);
        $this->assertEquals(1200000, $price->amount);
        $this->assertEquals(20, $price->discountpercent);
    }

    /**
     * Two promotions on one course is a merchandising decision, not a 45 per
     * cent course. The largest wins; they do not stack.
     */
    public function test_discounts_do_not_stack(): void {
        $this->resetAfterTest();
        $course = $this->paid_course(1000000);
        $this->attach_type($course->id, [
            'code' => 'p20', 'name_fr' => '-20 %', 'kind' => 'discount',
            'discount_percent' => 20, 'badge_style' => 'promo',
        ]);
        $this->attach_type($course->id, [
            'code' => 'p25', 'name_fr' => '-25 %', 'kind' => 'discount',
            'discount_percent' => 25, 'badge_style' => 'promo',
        ]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertEquals(25, $price->discountpercent);
        $this->assertEquals(750000, $price->amount);
    }

    /**
     * A free-granting type settles the price whatever the discount columns say.
     */
    public function test_free_type_wins_over_discount(): void {
        $this->resetAfterTest();
        $course = $this->paid_course(1500000);
        $this->attach_type($course->id, [
            'code' => 'p20b', 'name_fr' => '-20 %', 'kind' => 'discount',
            'discount_percent' => 20, 'badge_style' => 'promo',
        ]);
        $this->attach_type($course->id, [
            'code' => 'freeb', 'name_fr' => 'Gratuit', 'kind' => 'free',
            'grants_free_access' => 1, 'badge_style' => 'free',
        ]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertTrue($price->isfree);
        $this->assertEquals(0, $price->amount);
    }

    /**
     * A discount over 100 per cent is a typo, not a refund.
     */
    public function test_price_floors_at_zero(): void {
        $this->resetAfterTest();
        $course = $this->paid_course(500000);
        $this->attach_type($course->id, [
            'code' => 'p150', 'name_fr' => '-150 %', 'kind' => 'discount',
            'discount_percent' => 150, 'badge_style' => 'promo',
        ]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertEquals(0, $price->amount);
        $this->assertTrue($price->isfree);
    }

    /**
     * Deactivating a type stops it affecting price and stops it rendering,
     * without anyone detaching it from every course first.
     */
    public function test_inactive_type_is_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->paid_course(1000000);
        $typeid = $this->attach_type($course->id, [
            'code' => 'p30', 'name_fr' => '-30 %', 'kind' => 'discount',
            'discount_percent' => 30, 'badge_style' => 'promo',
        ]);
        $DB->set_field('local_pshub_course_type', 'is_active', 0, ['id' => $typeid]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertEquals(1000000, $price->amount);
        $this->assertCount(0, $price->badges);
    }

    /**
     * Badges come back in the administrator's display order, so a card shows
     * them the way the catalogue was designed rather than by insertion order.
     */
    public function test_badges_follow_sortorder(): void {
        $this->resetAfterTest();
        $course = $this->paid_course();
        $this->attach_type($course->id, [
            'code' => 'examb', 'name_fr' => 'Examen', 'name_en' => 'Exam',
            'kind' => 'exam', 'badge_style' => 'exam', 'sortorder' => 40,
        ]);
        $this->attach_type($course->id, [
            'code' => 'p20c', 'name_fr' => '-20 %', 'name_en' => '-20%',
            'kind' => 'discount', 'discount_percent' => 20,
            'badge_style' => 'promo', 'sortorder' => 10,
        ]);

        $price = (new price_resolver())->resolve($course->id);

        // PHPUnit runs in English, so the English labels are the expected ones.
        $this->assertCount(2, $price->badges);
        $this->assertEquals('-20%', $price->badges[0]->label);
        $this->assertEquals('Exam', $price->badges[1]->label);
    }

    /**
     * Course types are admin-created data, so their labels live in two columns
     * rather than a language pack. The interface language picks the column.
     *
     * This matters more than it looks: French is the platform's default
     * language (spec §12.4) but the test runner and a fresh Moodle both speak
     * English, so a test written in either language alone proves only half of
     * it - which is exactly how this test failed first time round.
     */
    public function test_labels_use_english_when_available(): void {
        $this->resetAfterTest();
        $course = $this->paid_course();
        $this->attach_type($course->id, [
            'code' => 'bilingual', 'name_fr' => 'Rentrée', 'name_en' => 'Back to school',
            'kind' => 'highlight', 'badge_style' => 'hot',
        ]);

        // The test runner speaks English.
        $this->assertEquals(
            'Back to school',
            (new price_resolver())->resolve($course->id)->badges[0]->label
        );
    }

    /**
     * The other branch of label(): an administrator who only fills in the
     * French name must not end up with an empty badge on an English screen.
     *
     * French is the platform default (spec §12.4) and the English column is
     * the optional one, so falling back to French is the correct direction.
     */
    public function test_label_falls_back_to_french_when_english_is_blank(): void {
        $this->resetAfterTest();
        $course = $this->paid_course();
        $this->attach_type($course->id, [
            'code' => 'fronly', 'name_fr' => 'Rentrée', 'name_en' => '',
            'kind' => 'highlight', 'badge_style' => 'hot',
        ]);

        $this->assertEquals(
            'Rentrée',
            (new price_resolver())->resolve($course->id)->badges[0]->label
        );
    }

    /**
     * AC-8 stated as a test: a type an administrator invents behaves correctly
     * with no code change and no deployment.
     */
    public function test_new_admin_created_type_needs_no_deployment(): void {
        $this->resetAfterTest();
        $course = $this->paid_course(2000000);
        $this->attach_type($course->id, [
            'code' => 'rentree', 'name_fr' => 'Rentrée -35 %',
            'name_en' => 'Back to school -35%', 'kind' => 'discount',
            'discount_percent' => 35, 'badge_style' => 'hot', 'sortorder' => 5,
        ]);

        $price = (new price_resolver())->resolve($course->id);

        $this->assertEquals(1300000, $price->amount);
        $this->assertEquals('Back to school -35%', $price->badges[0]->label);
        $this->assertEquals('hot', $price->badges[0]->style);
    }

    /**
     * XAF has no centimes, so the stored amount IS the amount.
     *
     * This is the test that stops the most expensive bug the catalogue could
     * have: formatting XAF the way one formats euros divides every price on
     * the site by a hundred, and a 15 000 FCFA course goes on sale for 150.
     * Nothing would throw. The site would simply be wrong about money, and an
     * accountant would find it before a test did.
     */
    public function test_xaf_has_no_subunit(): void {
        // Narrow no-break space between groups, no-break space before the
        // unit: the French convention, and French is the default language.
        $this->assertSame("15\u{202F}000\u{00A0}FCFA", price_resolver::format(15000));
        $this->assertSame("1\u{202F}500\u{202F}000\u{00A0}FCFA", price_resolver::format(1500000));
        $this->assertSame("0\u{00A0}FCFA", price_resolver::format(0));
    }

    /**
     * XOF is the same franc under a different code, and neither code is what
     * a learner in Douala reads. They read FCFA.
     */
    public function test_xof_is_also_a_franc_and_also_reads_fcfa(): void {
        $this->assertSame("2\u{202F}500\u{00A0}FCFA", price_resolver::format(2500, 'XOF'));
        $this->assertSame("2\u{202F}500\u{00A0}FCFA", price_resolver::format(2500, 'xof'));
    }

    /**
     * A currency that does have a subunit is still divided by a hundred, so
     * the zero-decimal list stays a statement about specific currencies and
     * not a decision to stop dividing altogether.
     */
    public function test_currencies_with_a_subunit_keep_their_decimals(): void {
        $this->assertSame("15,00\u{00A0}EUR", price_resolver::format(1500, 'EUR'));
    }

    /**
     * The four types the cahier names are seeded at install, so an
     * administrator starts with something rather than an empty table.
     */
    public function test_cahier_types_are_seeded(): void {
        global $DB;
        $this->resetAfterTest();

        foreach (['promo20', 'hot', 'free', 'exam'] as $code) {
            $this->assertTrue(
                $DB->record_exists('local_pshub_course_type', ['code' => $code]),
                "install.php should seed the '{$code}' course type"
            );
        }
    }
}
