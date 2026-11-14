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
use local_pshub\access\gating_service;
use local_pshub\local\constants;

/**
 * Tests for the gatekeeper.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\access\gating_service
 */
final class gating_service_test extends \advanced_testcase {

    /**
     * A Moodle course with its hub metadata.
     *
     * @param array $overrides Columns for local_pshub_course.
     * @return \stdClass The Moodle course.
     */
    private function hub_course(array $overrides = []): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_pshub_course', (object) array_merge([
            'courseid' => $course->id,
            'categoryid' => $course->category,
            'lang_code' => 'en',
            'cefr_level' => 'B1',
            'access_type' => constants::ACCESS_TYPE_PAID,
            'price_amount' => 1500000,
            'price_currency' => 'XAF',
            'review_status' => constants::REVIEW_PUBLISHED,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides));

        return $course;
    }

    /**
     * Create a pack containing a course, and grant a pack right.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Pack id.
     */
    private function pack_with_right(int $userid, int $courseid): int {
        global $DB;

        $packid = $DB->insert_record('local_pshub_pack', (object) [
            'name_fr' => 'Pack Anglais', 'name_en' => 'English pack',
            'price_amount' => 4000000, 'price_currency' => 'XAF',
            'is_active' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_pshub_pack_course', (object) [
            'packid' => $packid, 'courseid' => $courseid,
        ]);
        (new access_service())->grant($userid, 'pack', $packid, constants::SOURCE_PAYMENT);

        return (int) $packid;
    }

    /**
     * A free course opens for anyone, with no right and no enrolment.
     */
    public function test_free_course_grants(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course([
            'access_type' => constants::ACCESS_TYPE_FREE,
            'price_amount' => 0,
        ]);

        $this->assertTrue((new gating_service())->check($user->id, $course->id)->is_granted());
    }

    /**
     * A paid course with no right and no payment shows the paywall.
     */
    public function test_paid_course_without_right_denies(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_PAYMENT_REQUIRED, $result->get_reason());
    }

    /**
     * A valid course-level right opens it, and says which right did.
     */
    public function test_active_right_grants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        (new access_service())->grant($user->id, 'course', $course->id, constants::SOURCE_PAYMENT);
        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertTrue($result->is_granted());
        $this->assertGreaterThan(0, $result->get_accessid());
    }

    /**
     * A lapsed right is refused the moment it lapses, without waiting for the
     * expiry sweeper. And it is refused as "expired", not "payment required":
     * a returning customer gets a renewal, not a cold sell.
     */
    public function test_expired_right_denies_as_expired(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $now = time();

        (new access_service())->grant(
            $user->id, 'course', $course->id, constants::SOURCE_PAYMENT, $now - 60
        );
        $result = (new gating_service())->check($user->id, $course->id, $now);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_ACCESS_EXPIRED, $result->get_reason());
    }

    /**
     * A revoked right is not an expired one. Someone refunded gets the paywall
     * back, not a renewal prompt for access they no longer have a claim to.
     */
    public function test_revoked_right_denies_as_payment_required(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $service = new access_service();
        $right = $service->grant($user->id, 'course', $course->id, constants::SOURCE_PAYMENT);
        $service->revoke((int) $right->id, 'Refunded at learner request');

        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_PAYMENT_REQUIRED, $result->get_reason());
    }

    /**
     * Money has arrived but an administrator has not released it. This is not
     * a paywall and must not be styled as an error (spec §3.8, §15).
     */
    public function test_unvalidated_payment_denies_as_pending(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        $DB->insert_record('local_pshub_transaction', (object) [
            'userid' => $user->id,
            'target_type' => 'course',
            'target_id' => $course->id,
            'amount' => 1500000,
            'currency' => 'XAF',
            'method' => 'mtn_momo',
            'provider_ref' => 'ref-pending-1',
            'status' => constants::TXN_PAID_UNVALIDATED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_PAYMENT_PENDING, $result->get_reason());
    }

    /**
     * A course under review does not exist as far as a learner is concerned,
     * however they arrived at the URL (AC-1).
     */
    public function test_unpublished_course_hidden_from_learner(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course([
            'review_status' => constants::REVIEW_PENDING,
            'access_type' => constants::ACCESS_TYPE_FREE,
        ]);

        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_NOT_AVAILABLE, $result->get_reason());
    }

    /**
     * The same course is visible to someone who can approve it, or nobody
     * could review what they are being asked to publish.
     */
    public function test_unpublished_course_visible_to_approver(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $course = $this->hub_course([
            'review_status' => constants::REVIEW_PENDING,
            'access_type' => constants::ACCESS_TYPE_FREE,
        ]);

        $this->assertTrue((new gating_service())->check($admin->id, $course->id)->is_granted());
    }

    /**
     * A course this platform does not manage is refused rather than throwing a
     * stack trace at a learner who followed a stale link.
     */
    public function test_non_hub_course_denies(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_NOT_AVAILABLE, $result->get_reason());
    }

    /**
     * AC-18, first half. In a P1 build the pack branch cannot grant, EVEN FOR
     * A USER HOLDING A VALID PACK RIGHT. This is the assertion that makes
     * phase isolation a fact rather than a promise.
     */
    public function test_pack_right_grants_nothing_while_packs_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('packsenabled', 0, 'local_pshub');

        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->pack_with_right($user->id, $course->id);

        $result = (new gating_service())->check($user->id, $course->id);

        $this->assertFalse($result->is_granted());
        $this->assertEquals(constants::DENY_PAYMENT_REQUIRED, $result->get_reason());
    }

    /**
     * AC-18, second half. The same data, the same code, one flag. P2 opens the
     * branch with no schema change and no course reconfiguration.
     */
    public function test_pack_right_grants_once_packs_enabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('packsenabled', 1, 'local_pshub');

        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $this->pack_with_right($user->id, $course->id);

        $this->assertTrue((new gating_service())->check($user->id, $course->id)->is_granted());
    }

    /**
     * A retired pack stops opening its courses without anyone having to revoke
     * every right it ever issued.
     */
    public function test_inactive_pack_does_not_grant(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('packsenabled', 1, 'local_pshub');

        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();
        $packid = $this->pack_with_right($user->id, $course->id);
        $DB->set_field('local_pshub_pack', 'is_active', 0, ['id' => $packid]);

        $this->assertFalse((new gating_service())->check($user->id, $course->id)->is_granted());
    }

    /**
     * A "Gratuit" type attached by an administrator opens the course, even
     * though access_type still says paid. The badge is the price, so nobody
     * has to remember to change two things (spec §3.3).
     */
    public function test_free_granting_type_overrides_paid_access_type(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        // The 'free' type is seeded by db/install.php, so this is the real row.
        $typeid = $DB->get_field('local_pshub_course_type', 'id', ['code' => 'free']);
        $this->assertNotEmpty($typeid, 'install.php should have seeded the free course type');
        $DB->insert_record('local_pshub_course_type_map', (object) [
            'courseid' => $course->id, 'typeid' => $typeid,
        ]);

        $this->assertTrue((new gating_service())->check($user->id, $course->id)->is_granted());
    }

    /**
     * require_access() throws for callers where a denial is exceptional, and
     * the exception carries the learner-facing string rather than the
     * identifier (AC-17).
     */
    public function test_require_access_throws_with_a_human_message(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->hub_course();

        try {
            (new gating_service())->require_access($user->id, $course->id);
            $this->fail('require_access() should have thrown for an unpaid course');
        } catch (\moodle_exception $e) {
            $this->assertStringNotContainsString('payment_required', $e->getMessage());
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
