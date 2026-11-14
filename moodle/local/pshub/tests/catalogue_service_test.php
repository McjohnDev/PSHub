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

use local_pshub\catalogue\catalogue_service;
use local_pshub\local\constants;

/**
 * The public catalogue.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\catalogue\catalogue_service
 */
final class catalogue_service_test extends \advanced_testcase {

    /**
     * A hub course with sensible defaults.
     *
     * @param array $overrides
     * @param string|null $fullname
     * @return \stdClass The Moodle course.
     */
    private function hub_course(array $overrides = [], ?string $fullname = null): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course(
            $fullname ? ['fullname' => $fullname] : []
        );
        $DB->insert_record('local_pshub_course', (object) array_merge([
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'lang_code' => 'en',
            'cefr_level' => 'B1',
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 1500000,
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
     * Attach a seeded course type by its code.
     *
     * @param int $courseid
     * @param string $code
     * @return void
     */
    private function attach_seeded_type(int $courseid, string $code): void {
        global $DB;

        $typeid = $DB->get_field('local_pshub_course_type', 'id', ['code' => $code], MUST_EXIST);
        $DB->insert_record('local_pshub_course_type_map',
            (object) ['courseid' => $courseid, 'typeid' => $typeid]);
    }

    /**
     * AC-1's public half. A course under review must not appear anywhere a
     * visitor can see: not in search, not in featured, not as a facet, and not
     * by guessing its URL.
     */
    public function test_unpublished_courses_are_invisible_everywhere(): void {
        $this->resetAfterTest();

        $this->hub_course(['review_status' => constants::REVIEW_PUBLISHED], 'Published course');
        $pending = $this->hub_course([
            'review_status' => constants::REVIEW_PENDING,
            'is_featured' => 1,
            'lang_code' => 'de',
        ], 'Pending course');
        $this->hub_course([
            'review_status' => constants::REVIEW_DRAFT,
            'is_featured' => 1,
        ], 'Draft course');

        $service = new catalogue_service();

        $this->assertCount(1, $service->search()->courses);
        $this->assertEquals(1, $service->search()->total);
        $this->assertCount(0, $service->get_featured());
        $this->assertFalse($service->get_course($pending->id));
        $this->assertNotContains('de', $service->get_facets()->langs);
    }

    /**
     * Featured courses come back in the administrator's chosen order, not
     * whatever order the database felt like.
     */
    public function test_featured_follows_admin_order(): void {
        $this->resetAfterTest();

        $this->hub_course(['is_featured' => 1, 'featured_order' => 30], 'Third');
        $this->hub_course(['is_featured' => 1, 'featured_order' => 10], 'First');
        $this->hub_course(['is_featured' => 1, 'featured_order' => 20], 'Second');
        $this->hub_course(['is_featured' => 0], 'Not featured');

        $featured = (new catalogue_service())->get_featured();

        $this->assertCount(3, $featured);
        $this->assertEquals('First', $featured[0]->fullname);
        $this->assertEquals('Second', $featured[1]->fullname);
        $this->assertEquals('Third', $featured[2]->fullname);
    }

    /**
     * Filters narrow, and they combine.
     */
    public function test_filters_narrow_and_combine(): void {
        $this->resetAfterTest();

        $this->hub_course(['lang_code' => 'en', 'cefr_level' => 'B1'], 'English B1');
        $this->hub_course(['lang_code' => 'en', 'cefr_level' => 'C1'], 'English C1');
        $this->hub_course(['lang_code' => 'es', 'cefr_level' => 'B1'], 'Spanish B1');

        $service = new catalogue_service();

        $this->assertEquals(2, $service->search(['lang' => 'en'])->total);
        $this->assertEquals(2, $service->search(['level' => 'B1'])->total);
        $this->assertEquals(1, $service->search(['lang' => 'en', 'level' => 'B1'])->total);
        $this->assertEquals(0, $service->search(['lang' => 'es', 'level' => 'C1'])->total);
    }

    /**
     * Free and paid are separable, because "what can I try for nothing" is the
     * first question a new visitor asks.
     */
    public function test_access_filter(): void {
        $this->resetAfterTest();

        $this->hub_course(['access_type' => constants::ACCESS_TYPE_FREE, 'price_amount' => 0]);
        $this->hub_course(['access_type' => constants::ACCESS_TYPE_PAID]);
        $this->hub_course(['access_type' => constants::ACCESS_TYPE_PAID]);

        $service = new catalogue_service();

        $this->assertEquals(1, $service->search(['access' => 'free'])->total);
        $this->assertEquals(2, $service->search(['access' => 'paid'])->total);
    }

    /**
     * A course carrying several types must appear once, not once per type.
     * The type join is the classic place to accidentally multiply rows.
     */
    public function test_type_filter_does_not_duplicate_courses(): void {
        $this->resetAfterTest();

        $course = $this->hub_course([], 'Multi-badge course');
        $this->attach_seeded_type($course->id, 'promo20');
        $this->attach_seeded_type($course->id, 'hot');

        $service = new catalogue_service();

        $this->assertEquals(1, $service->search(['typecode' => 'hot'])->total);
        $this->assertCount(1, $service->search(['typecode' => 'hot'])->courses);
        // And unfiltered, it is still one course despite carrying two types.
        $this->assertEquals(1, $service->search()->total);
    }

    /**
     * Search by title, case-insensitively.
     */
    public function test_text_search(): void {
        $this->resetAfterTest();

        $this->hub_course([], 'Anglais professionnel B1');
        $this->hub_course([], 'Espagnol debutant');

        $service = new catalogue_service();

        $this->assertEquals(1, $service->search(['q' => 'anglais'])->total);
        $this->assertEquals(1, $service->search(['q' => 'ANGLAIS'])->total);
        $this->assertEquals(0, $service->search(['q' => 'allemand'])->total);
    }

    /**
     * The catalogue price must agree with the price resolver, because the
     * paywall uses the resolver. A card advertising one figure and a checkout
     * charging another is the worst bug this area can have.
     */
    public function test_card_price_matches_the_resolver(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(['price_amount' => 1500000]);
        $this->attach_seeded_type($course->id, 'promo20');

        $presented = (new catalogue_service())->get_course($course->id);

        $this->assertEquals(1500000, $presented->pricebase);
        $this->assertEquals(1200000, $presented->price);
        $this->assertEquals(20, $presented->discountpercent);
        $this->assertFalse($presented->isfree);
    }

    /**
     * A free-granting type makes the card say free, without anyone having to
     * change access_type as well.
     */
    public function test_free_type_shows_as_free_on_the_card(): void {
        $this->resetAfterTest();

        $course = $this->hub_course(['price_amount' => 1500000]);
        $this->attach_seeded_type($course->id, 'free');

        $presented = (new catalogue_service())->get_course($course->id);

        $this->assertTrue($presented->isfree);
        $this->assertEquals(0, $presented->price);
    }

    /**
     * Paging returns disjoint pages and a stable total.
     */
    public function test_paging(): void {
        $this->resetAfterTest();

        for ($i = 1; $i <= 5; $i++) {
            $this->hub_course([], sprintf('Course %02d', $i));
        }

        $service = new catalogue_service();

        $first = $service->search([], 0, 2);
        $second = $service->search([], 1, 2);
        $third = $service->search([], 2, 2);

        $this->assertEquals(5, $first->total);
        $this->assertCount(2, $first->courses);
        $this->assertCount(2, $second->courses);
        $this->assertCount(1, $third->courses);

        $names = array_merge(
            array_column($first->courses, 'fullname'),
            array_column($second->courses, 'fullname'),
            array_column($third->courses, 'fullname')
        );
        $this->assertCount(5, array_unique($names));
    }

    /**
     * Facets offer only values that actually return something, so the
     * catalogue never advertises a filter leading to an empty page.
     */
    public function test_facets_reflect_published_content_only(): void {
        $this->resetAfterTest();

        $this->hub_course(['lang_code' => 'en', 'cefr_level' => 'B1']);
        $this->hub_course(['lang_code' => 'es', 'cefr_level' => 'A1']);

        $facets = (new catalogue_service())->get_facets();

        sort($facets->langs);
        sort($facets->levels);
        $this->assertEquals(['en', 'es'], $facets->langs);
        $this->assertEquals(['A1', 'B1'], $facets->levels);
    }

    /**
     * A course with an image offers it; one without falls back to the plate.
     *
     * The URL is OURS, not pluginfile.php's. Moodle serves course files
     * through pluginfile.php, which honours $CFG->forcelogin, so on a public
     * catalogue that URL redirects an anonymous visitor's browser to the login
     * page instead of returning a picture. local/pshub/image.php serves the
     * same file under the catalogue's rule: published is public (AC-2).
     */
    public function test_a_course_image_is_offered_through_the_public_endpoint(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $context = \context_course::instance($course->id);

        // A real PNG, because Moodle only accepts a file it can read as an
        // image: 8x8, one colour.
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'cover.png',
        ], $png);

        // Core caches the resolved URL and refreshes it when the course is
        // saved; a file written straight into the area has to say so.
        \cache::make('core', 'course_image')->delete($course->id);

        $presented = (new catalogue_service())->get_course($course->id);

        $this->assertTrue($presented->hasimage);
        $this->assertStringContainsString('/local/pshub/image.php', $presented->imageurl);
        $this->assertStringNotContainsString('pluginfile.php', $presented->imageurl);
    }

    /**
     * No image means no image. The card shows the language plate instead, and
     * a course is publishable without anyone finding a photograph first.
     */
    public function test_a_course_without_an_image_says_so(): void {
        $this->resetAfterTest();

        $course = $this->hub_course();
        $presented = (new catalogue_service())->get_course($course->id);

        $this->assertFalse($presented->hasimage);
        $this->assertNull($presented->imageurl);
        $this->assertNotEmpty($presented->langplate);
    }
}
