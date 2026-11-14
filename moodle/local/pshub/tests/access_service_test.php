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
use local_pshub\audit\audit_service;
use local_pshub\local\constants;

/**
 * The access-right lifecycle.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\access\access_service
 */
final class access_service_test extends \advanced_testcase {

    /**
     * Granting twice for one target updates a single row rather than failing
     * on the unique key or accumulating duplicates.
     */
    public function test_grant_is_idempotent_per_target(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $service = new access_service();

        $service->grant($user->id, 'course', 7, constants::SOURCE_AUTO);
        $service->grant($user->id, 'course', 7, constants::SOURCE_PAYMENT);

        $rows = $DB->get_records('local_pshub_access', [
            'userid' => $user->id, 'target_type' => 'course', 'target_id' => 7,
        ]);

        $this->assertCount(1, $rows);
        $this->assertEquals(constants::SOURCE_PAYMENT, reset($rows)->source);
    }

    /**
     * A revoked right can be granted again, for instance when a learner pays a
     * second time after a refund.
     */
    public function test_revoked_right_can_be_reactivated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $service = new access_service();

        $right = $service->grant($user->id, 'course', 9, constants::SOURCE_PAYMENT);
        $service->revoke((int) $right->id, 'Refunded');
        $this->assertFalse($service->is_usable($service->find($user->id, 'course', 9)));

        $service->grant($user->id, 'course', 9, constants::SOURCE_PAYMENT);

        $this->assertTrue($service->is_usable($service->find($user->id, 'course', 9)));
    }

    /**
     * A manual grant without a reason is a bug, not a supported call. Support
     * grants are the ones questioned months later, so the reason is required
     * at the moment of writing rather than reconstructed afterwards.
     */
    public function test_manual_grant_requires_a_reason(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\coding_exception::class);
        (new access_service())->grant($user->id, 'course', 3, constants::SOURCE_MANUAL);
    }

    /**
     * Decisions a person made or money caused are audited. Automatic grants on
     * free courses are not, or the trail would be nothing but noise.
     */
    public function test_audit_covers_human_and_money_grants_only(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $service = new access_service();

        $service->grant($user->id, 'course', 1, constants::SOURCE_AUTO);
        $this->assertEquals(0, $DB->count_records('local_pshub_audit', [
            'action' => audit_service::ACTION_ACCESS_GRANTED,
        ]));

        $service->grant($user->id, 'course', 2, constants::SOURCE_MANUAL, 0, 'Goodwill after an outage');
        $this->assertEquals(1, $DB->count_records('local_pshub_audit', [
            'action' => audit_service::ACTION_ACCESS_GRANTED,
        ]));
    }

    /**
     * A perpetual right never lapses. Expiry is opt-in, so a missing expiry
     * date cannot quietly lock someone out.
     */
    public function test_perpetual_right_never_expires(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $service = new access_service();

        $service->grant($user->id, 'course', 4, constants::SOURCE_PAYMENT, 0);

        $this->assertTrue($service->is_usable(
            $service->find($user->id, 'course', 4),
            time() + YEARSECS * 5
        ));
    }

    /**
     * The sweeper marks lapsed rights expired for reporting. Correctness does
     * not depend on it having run, which is why this asserts the count and the
     * stored status rather than an access decision.
     */
    public function test_expire_due_marks_only_lapsed_rights(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $service = new access_service();
        $now = time();

        $service->grant($user->id, 'course', 11, constants::SOURCE_PAYMENT, $now - 10);
        $service->grant($user->id, 'course', 12, constants::SOURCE_PAYMENT, $now + HOURSECS);
        $service->grant($user->id, 'course', 13, constants::SOURCE_PAYMENT, 0);

        $this->assertEquals(1, $service->expire_due($now));
        $this->assertEquals(constants::ACCESS_EXPIRED, $service->find($user->id, 'course', 11)->status);
        $this->assertEquals(constants::ACCESS_ACTIVE, $service->find($user->id, 'course', 12)->status);
        $this->assertEquals(constants::ACCESS_ACTIVE, $service->find($user->id, 'course', 13)->status);
    }

    /**
     * Only active packs are considered. Retiring a pack stops it opening
     * courses without anyone revoking every right it issued.
     */
    public function test_packs_containing_course_ignores_inactive_packs(): void {
        global $DB;
        $this->resetAfterTest();

        $active = $DB->insert_record('local_pshub_pack', (object) [
            'name_fr' => 'Actif', 'name_en' => 'Active', 'price_amount' => 100,
            'price_currency' => 'XAF', 'is_active' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $retired = $DB->insert_record('local_pshub_pack', (object) [
            'name_fr' => 'Retiré', 'name_en' => 'Retired', 'price_amount' => 100,
            'price_currency' => 'XAF', 'is_active' => 0, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_pshub_pack_course', (object) ['packid' => $active, 'courseid' => 55]);
        $DB->insert_record('local_pshub_pack_course', (object) ['packid' => $retired, 'courseid' => 55]);

        $this->assertEquals([(int) $active], (new access_service())->packs_containing_course(55));
    }

    /**
     * is_usable() answers on the passage of time, not on what a cron run has
     * noticed. A right that lapsed a minute ago is already unusable.
     */
    public function test_usability_follows_the_clock_not_the_sweeper(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $service = new access_service();
        $now = time();

        $service->grant($user->id, 'course', 21, constants::SOURCE_PAYMENT, $now + 60);
        $right = $service->find($user->id, 'course', 21);

        $this->assertTrue($service->is_usable($right, $now));
        $this->assertFalse($service->is_usable($right, $now + 61));
        // Still recorded as active: the sweeper has not run, and need not have.
        $this->assertEquals(constants::ACCESS_ACTIVE, $right->status);
    }
}
